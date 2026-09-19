<?php

declare( strict_types=1 );

use ArtisanPackUI\GoogleBusinessProfile\Contracts\TokenProvider;
use ArtisanPackUI\GoogleBusinessProfile\Exceptions\ApiException;
use ArtisanPackUI\GoogleBusinessProfile\LocalPosts\DataTransferObjects\LocalPost;
use ArtisanPackUI\GoogleBusinessProfile\LocalPosts\DataTransferObjects\LocalPostList;
use ArtisanPackUI\GoogleBusinessProfile\LocalPosts\LocalPostsClient;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Request;

class StubLocalPostsTokenProvider implements TokenProvider
{
    public function __construct( public string $token = 'stub-local-posts-token' )
    {
    }

    public function accessToken(): string
    {
        return $this->token;
    }
}

function gbpLocalPostsClient(
    HttpFactory $http,
    ?TokenProvider $provider = null,
    int $maxAttempts = 1,
): LocalPostsClient {
    return new LocalPostsClient(
        tokenProvider: $provider ?? new StubLocalPostsTokenProvider(),
        http: $http,
        timeout: 5,
        maxAttempts: $maxAttempts,
        retrySleepMs: 0,
    );
}

test( 'createLocalPost POSTs the payload against the location\'s /localPosts sub-resource', function (): void {
    $factory = new HttpFactory();
    $factory->fake( [
        'mybusiness.googleapis.com/v4/accounts/1/locations/2/localPosts' => $factory::response(
            [
                'name'         => 'accounts/1/locations/2/localPosts/abc',
                'summary'      => 'We just launched!',
                'languageCode' => 'en',
                'topicType'    => 'STANDARD',
                'state'        => 'LIVE',
                'createTime'   => '2026-08-14T18:00:00Z',
                'searchUrl'    => 'https://posts.google.com/abc',
            ],
            200,
        ),
    ] );

    $client = gbpLocalPostsClient( $factory, new StubLocalPostsTokenProvider( 'the-token' ) );

    $payload = [
        'languageCode' => 'en',
        'summary'      => 'We just launched!',
        'topicType'    => 'STANDARD',
    ];

    $post = $client->createLocalPost( 'accounts/1/locations/2', $payload );

    expect( $post )->toBeInstanceOf( LocalPost::class );
    expect( $post->name )->toBe( 'accounts/1/locations/2/localPosts/abc' );
    expect( $post->summary )->toBe( 'We just launched!' );
    expect( $post->topicType )->toBe( 'STANDARD' );
    expect( $post->state )->toBe( 'LIVE' );
    expect( $post->searchUrl )->toBe( 'https://posts.google.com/abc' );

    $factory->assertSent( function ( Request $request ) use ( $payload ): bool {
        return 'POST' === $request->method()
            && 'https://mybusiness.googleapis.com/v4/accounts/1/locations/2/localPosts' === $request->url()
            && 'Bearer the-token' === $request->header( 'Authorization' )[0]
            && str_contains( $request->header( 'Content-Type' )[0], 'application/json' )
            && $payload === $request->data();
    } );
} );

test( 'createLocalPost forwards nested typed sub-resources (callToAction, event, offer, media) verbatim', function (): void {
    $factory = new HttpFactory();
    $factory->fake( [
        '*' => $factory::response(
            [
                'name'         => 'accounts/1/locations/2/localPosts/evt',
                'topicType'    => 'EVENT',
                'state'        => 'LIVE',
                'summary'      => 'Grand opening',
                'callToAction' => [ 'actionType' => 'LEARN_MORE', 'url' => 'https://example.test/open' ],
                'event'        => [
                    'title'    => 'Grand opening',
                    'schedule' => [
                        'startDate' => [ 'year' => 2026, 'month' => 10, 'day' => 1 ],
                        'endDate'   => [ 'year' => 2026, 'month' => 10, 'day' => 1 ],
                        'startTime' => [ 'hours' => 17, 'minutes' => 0 ],
                        'endTime'   => [ 'hours' => 20, 'minutes' => 0 ],
                    ],
                ],
                'media'        => [
                    [ 'mediaFormat' => 'PHOTO', 'sourceUrl' => 'https://cdn.example.test/hero.jpg' ],
                ],
            ],
            200,
        ),
    ] );

    $client = gbpLocalPostsClient( $factory );

    $post = $client->createLocalPost( 'accounts/1/locations/2', [
        'topicType' => 'EVENT',
        'summary'   => 'Grand opening',
    ] );

    expect( $post->hasCallToAction() )->toBeTrue();
    expect( $post->callToAction->actionType )->toBe( 'LEARN_MORE' );
    expect( $post->callToAction->url )->toBe( 'https://example.test/open' );
    expect( $post->event )->not->toBeNull();
    expect( $post->event->title )->toBe( 'Grand opening' );
    expect( $post->event->startDate )->toBe( [ 'year' => 2026, 'month' => 10, 'day' => 1 ] );
    expect( $post->event->endTime )->toBe( [ 'hours' => 20, 'minutes' => 0 ] );
    expect( $post->hasMedia() )->toBeTrue();
    expect( $post->media[0]->mediaFormat )->toBe( 'PHOTO' );
    expect( $post->media[0]->sourceUrl )->toBe( 'https://cdn.example.test/hero.jpg' );
} );

test( 'createLocalPost surfaces the offer sub-resource for OFFER topic type', function (): void {
    $factory = new HttpFactory();
    $factory->fake( [
        '*' => $factory::response(
            [
                'name'      => 'accounts/1/locations/2/localPosts/offer1',
                'topicType' => 'OFFER',
                'offer'     => [
                    'couponCode'      => 'SAVE10',
                    'redeemOnlineUrl' => 'https://example.test/redeem',
                    'termsConditions' => 'One per customer.',
                ],
            ],
            200,
        ),
    ] );

    $client = gbpLocalPostsClient( $factory );

    $post = $client->createLocalPost( 'accounts/1/locations/2', [ 'topicType' => 'OFFER' ] );

    expect( $post->offer )->not->toBeNull();
    expect( $post->offer->couponCode )->toBe( 'SAVE10' );
    expect( $post->offer->redeemOnlineUrl )->toBe( 'https://example.test/redeem' );
    expect( $post->offer->termsConditions )->toBe( 'One per customer.' );
} );

test( 'createLocalPost rejects an empty parent without dispatching', function (): void {
    $factory = new HttpFactory();
    $factory->fake( [ '*' => $factory::response( [], 200 ) ] );

    $client = gbpLocalPostsClient( $factory );

    expect( fn (): LocalPost => $client->createLocalPost( '', [ 'summary' => 'x' ] ) )
        ->toThrow( InvalidArgumentException::class );

    $factory->assertSentCount( 0 );
} );

test( 'createLocalPost rejects an empty payload without dispatching', function (): void {
    $factory = new HttpFactory();
    $factory->fake( [ '*' => $factory::response( [], 200 ) ] );

    $client = gbpLocalPostsClient( $factory );

    expect( fn (): LocalPost => $client->createLocalPost( 'accounts/1/locations/2', [] ) )
        ->toThrow( InvalidArgumentException::class );

    $factory->assertSentCount( 0 );
} );

test( 'createLocalPost maps API errors to ApiException carrying the status and body', function (): void {
    $factory = new HttpFactory();
    $factory->fake( [ '*' => $factory::response( 'invalid summary', 400 ) ] );

    $client = gbpLocalPostsClient( $factory );

    $thrown = null;

    try {
        $client->createLocalPost( 'accounts/1/locations/2', [ 'summary' => 'x' ] );
    } catch ( ApiException $exception ) {
        $thrown = $exception;
    }

    expect( $thrown )->not->toBeNull();
    expect( $thrown->statusCode() )->toBe( 400 );
    expect( $thrown->responseBody() )->toBe( 'invalid summary' );
} );

test( 'listLocalPosts hits the v4 legacy host with a Bearer token and returns typed DTOs', function (): void {
    $factory = new HttpFactory();
    $factory->fake( [
        'mybusiness.googleapis.com/v4/accounts/1/locations/2/localPosts*' => $factory::response(
            [
                'localPosts' => [
                    [
                        'name'      => 'accounts/1/locations/2/localPosts/a',
                        'summary'   => 'Hello',
                        'topicType' => 'STANDARD',
                        'state'     => 'LIVE',
                    ],
                    [
                        'name'      => 'accounts/1/locations/2/localPosts/b',
                        'topicType' => 'OFFER',
                        'offer'     => [ 'couponCode' => 'HELLO' ],
                    ],
                ],
            ],
            200,
        ),
    ] );

    $client = gbpLocalPostsClient( $factory, new StubLocalPostsTokenProvider( 'the-token' ) );

    $result = $client->listLocalPosts( 'accounts/1/locations/2' );

    expect( $result )->toBeInstanceOf( LocalPostList::class );
    expect( $result->localPosts )->toHaveCount( 2 );
    expect( $result->localPosts[0]->name )->toBe( 'accounts/1/locations/2/localPosts/a' );
    expect( $result->localPosts[0]->summary )->toBe( 'Hello' );
    expect( $result->localPosts[1]->topicType )->toBe( 'OFFER' );
    expect( $result->localPosts[1]->offer->couponCode )->toBe( 'HELLO' );
    expect( $result->hasMore() )->toBeFalse();

    $factory->assertSent( function ( Request $request ): bool {
        return 'GET' === $request->method()
            && str_starts_with( $request->url(), 'https://mybusiness.googleapis.com/v4/accounts/1/locations/2/localPosts' )
            && 'Bearer the-token' === $request->header( 'Authorization' )[0]
            && str_contains( $request->header( 'Accept' )[0], 'application/json' );
    } );
} );

test( 'listLocalPosts appends pageSize and pageToken when provided', function (): void {
    $factory = new HttpFactory();
    $factory->fake( [ '*' => $factory::response( [ 'localPosts' => [] ], 200 ) ] );

    $client = gbpLocalPostsClient( $factory );

    $client->listLocalPosts(
        parent   : 'accounts/1/locations/2',
        pageSize : 50,
        pageToken: 'next-page',
    );

    $factory->assertSent( function ( Request $request ): bool {
        $url = $request->url();

        return str_contains( $url, 'pageSize=50' )
            && str_contains( $url, 'pageToken=next-page' );
    } );
} );

test( 'listLocalPosts omits query parameters that were not supplied', function (): void {
    $factory = new HttpFactory();
    $factory->fake( [ '*' => $factory::response( [ 'localPosts' => [] ], 200 ) ] );

    $client = gbpLocalPostsClient( $factory );

    $client->listLocalPosts( 'accounts/1/locations/2' );

    $factory->assertSent( function ( Request $request ): bool {
        return 'https://mybusiness.googleapis.com/v4/accounts/1/locations/2/localPosts' === $request->url();
    } );
} );

test( 'listLocalPosts surfaces the nextPageToken so callers can iterate pages', function (): void {
    $factory = new HttpFactory();
    $factory->fake( [
        '*' => $factory::response(
            [
                'localPosts'    => [ [ 'name' => 'accounts/1/locations/2/localPosts/a' ] ],
                'nextPageToken' => 'page-2',
            ],
            200,
        ),
    ] );

    $client = gbpLocalPostsClient( $factory );

    $result = $client->listLocalPosts( 'accounts/1/locations/2', pageSize: 1 );

    expect( $result->nextPageToken )->toBe( 'page-2' );
    expect( $result->hasMore() )->toBeTrue();
} );

test( 'listLocalPosts rejects an empty parent without dispatching', function (): void {
    $factory = new HttpFactory();
    $factory->fake( [ '*' => $factory::response( [ 'localPosts' => [] ], 200 ) ] );

    $client = gbpLocalPostsClient( $factory );

    expect( fn (): LocalPostList => $client->listLocalPosts( '' ) )
        ->toThrow( InvalidArgumentException::class );

    $factory->assertSentCount( 0 );
} );

test( 'listLocalPosts rejects pageSize outside the documented 1..MAX_PAGE_SIZE range without making a request', function (): void {
    $factory = new HttpFactory();
    $factory->fake( [ '*' => $factory::response( [ 'localPosts' => [] ], 200 ) ] );

    $client = gbpLocalPostsClient( $factory );

    expect( fn (): LocalPostList => $client->listLocalPosts( 'accounts/1/locations/2', pageSize: 0 ) )
        ->toThrow( InvalidArgumentException::class );

    expect( fn (): LocalPostList => $client->listLocalPosts( 'accounts/1/locations/2', pageSize: LocalPostsClient::MAX_PAGE_SIZE + 1 ) )
        ->toThrow( InvalidArgumentException::class );

    $factory->assertSentCount( 0 );
} );

test( 'listLocalPosts treats an empty payload as a page with zero posts', function (): void {
    $factory = new HttpFactory();
    $factory->fake( [ '*' => $factory::response( [], 200 ) ] );

    $client = gbpLocalPostsClient( $factory );

    $result = $client->listLocalPosts( 'accounts/1/locations/2' );

    expect( $result->localPosts )->toBe( [] );
    expect( $result->nextPageToken )->toBeNull();
} );

test( 'listLocalPosts maps API errors to ApiException carrying the status and body', function (): void {
    $factory = new HttpFactory();
    $factory->fake( [ '*' => $factory::response( 'forbidden', 403 ) ] );

    $client = gbpLocalPostsClient( $factory );

    $thrown = null;

    try {
        $client->listLocalPosts( 'accounts/1/locations/2' );
    } catch ( ApiException $exception ) {
        $thrown = $exception;
    }

    expect( $thrown )->not->toBeNull();
    expect( $thrown->statusCode() )->toBe( 403 );
    expect( $thrown->responseBody() )->toBe( 'forbidden' );
} );

test( 'deleteLocalPost issues a DELETE against the post\'s resource name and returns void', function (): void {
    $factory = new HttpFactory();
    $factory->fake( [
        'mybusiness.googleapis.com/v4/accounts/1/locations/2/localPosts/abc' => $factory::response( '', 200 ),
    ] );

    $client = gbpLocalPostsClient( $factory, new StubLocalPostsTokenProvider( 'the-token' ) );

    $client->deleteLocalPost( 'accounts/1/locations/2/localPosts/abc' );

    $factory->assertSent( function ( Request $request ): bool {
        return 'DELETE' === $request->method()
            && 'https://mybusiness.googleapis.com/v4/accounts/1/locations/2/localPosts/abc' === $request->url()
            && 'Bearer the-token' === $request->header( 'Authorization' )[0];
    } );
} );

test( 'deleteLocalPost rejects an empty name without dispatching', function (): void {
    $factory = new HttpFactory();
    $factory->fake( [ '*' => $factory::response( '', 200 ) ] );

    $client = gbpLocalPostsClient( $factory );

    expect( fn () => $client->deleteLocalPost( '' ) )
        ->toThrow( InvalidArgumentException::class );

    $factory->assertSentCount( 0 );
} );

test( 'deleteLocalPost maps API errors to ApiException carrying the status and body', function (): void {
    $factory = new HttpFactory();
    $factory->fake( [ '*' => $factory::response( 'not found', 404 ) ] );

    $client = gbpLocalPostsClient( $factory );

    $thrown = null;

    try {
        $client->deleteLocalPost( 'accounts/1/locations/2/localPosts/abc' );
    } catch ( ApiException $exception ) {
        $thrown = $exception;
    }

    expect( $thrown )->not->toBeNull();
    expect( $thrown->statusCode() )->toBe( 404 );
    expect( $thrown->responseBody() )->toBe( 'not found' );
} );

test( 'LocalPost::fromArray preserves the raw payload and coerces missing fields to null', function (): void {
    $post = LocalPost::fromArray( [
        'name'      => 'accounts/1/locations/2/localPosts/x',
        'summary'   => 'Hello',
        'topicType' => 'STANDARD',
        'weirdKey'  => 'preserved',
    ] );

    expect( $post->name )->toBe( 'accounts/1/locations/2/localPosts/x' );
    expect( $post->languageCode )->toBeNull();
    expect( $post->callToAction )->toBeNull();
    expect( $post->event )->toBeNull();
    expect( $post->offer )->toBeNull();
    expect( $post->media )->toBe( [] );
    expect( $post->raw['weirdKey'] )->toBe( 'preserved' );
    expect( $post->hasCallToAction() )->toBeFalse();
    expect( $post->hasMedia() )->toBeFalse();
} );
