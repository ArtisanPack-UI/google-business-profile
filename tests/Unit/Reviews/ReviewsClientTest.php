<?php

declare( strict_types=1 );

use ArtisanPackUI\GoogleBusinessProfile\Contracts\TokenProvider;
use ArtisanPackUI\GoogleBusinessProfile\Exceptions\ApiException;
use ArtisanPackUI\GoogleBusinessProfile\Reviews\DataTransferObjects\ReviewList;
use ArtisanPackUI\GoogleBusinessProfile\Reviews\DataTransferObjects\ReviewReply;
use ArtisanPackUI\GoogleBusinessProfile\Reviews\ReviewsClient;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Request;

class StubReviewsTokenProvider implements TokenProvider
{
    public function __construct( public string $token = 'stub-reviews-token' )
    {
    }

    public function accessToken(): string
    {
        return $this->token;
    }
}

function gbpReviewsClient(
    HttpFactory $http,
    ?TokenProvider $provider = null,
    int $maxAttempts = 1,
): ReviewsClient {
    return new ReviewsClient(
        tokenProvider: $provider ?? new StubReviewsTokenProvider(),
        http: $http,
        timeout: 5,
        maxAttempts: $maxAttempts,
        retrySleepMs: 0,
    );
}

test( 'listReviews hits the v4 legacy host with a Bearer token and returns typed DTOs', function (): void {
    $factory = new HttpFactory();
    $factory->fake( [
        'mybusiness.googleapis.com/v4/accounts/1/locations/2/reviews*' => $factory::response(
            [
                'reviews'          => [
                    [
                        'name'       => 'accounts/1/locations/2/reviews/a',
                        'reviewId'   => 'a',
                        'starRating' => 'FIVE',
                        'comment'    => 'Great!',
                        'reviewer'   => [ 'displayName' => 'Jamie Q.' ],
                    ],
                    [
                        'name'        => 'accounts/1/locations/2/reviews/b',
                        'reviewId'    => 'b',
                        'starRating'  => 'FOUR',
                        'reviewReply' => [ 'comment' => 'Thanks!' ],
                    ],
                ],
                'averageRating'    => 4.5,
                'totalReviewCount' => 2,
            ],
            200,
        ),
    ] );

    $client = gbpReviewsClient( $factory, new StubReviewsTokenProvider( 'the-token' ) );

    $result = $client->listReviews( 'accounts/1/locations/2' );

    expect( $result )->toBeInstanceOf( ReviewList::class );
    expect( $result->reviews )->toHaveCount( 2 );
    expect( $result->reviews[0]->reviewId )->toBe( 'a' );
    expect( $result->reviews[0]->reviewer->displayName )->toBe( 'Jamie Q.' );
    expect( $result->reviews[0]->numericRating() )->toBe( 5 );
    expect( $result->reviews[1]->hasReply() )->toBeTrue();
    expect( $result->reviews[1]->reviewReply->comment )->toBe( 'Thanks!' );
    expect( $result->averageRating )->toBe( 4.5 );
    expect( $result->totalReviewCount )->toBe( 2 );
    expect( $result->hasMore() )->toBeFalse();

    $factory->assertSent( function ( Request $request ): bool {
        return 'GET' === $request->method()
            && str_starts_with( $request->url(), 'https://mybusiness.googleapis.com/v4/accounts/1/locations/2/reviews' )
            && 'Bearer the-token' === $request->header( 'Authorization' )[0]
            && str_contains( $request->header( 'Accept' )[0], 'application/json' );
    } );
} );

test( 'listReviews appends pageSize, pageToken, and orderBy when provided', function (): void {
    $factory = new HttpFactory();
    $factory->fake( [ '*' => $factory::response( [ 'reviews' => [] ], 200 ) ] );

    $client = gbpReviewsClient( $factory );

    $client->listReviews(
        parent   : 'accounts/1/locations/2',
        pageSize : 25,
        pageToken: 'next-page',
        orderBy  : 'updateTime desc',
    );

    $factory->assertSent( function ( Request $request ): bool {
        $url = $request->url();

        return str_contains( $url, 'pageSize=25' )
            && str_contains( $url, 'pageToken=next-page' )
            && str_contains( $url, 'orderBy=updateTime%20desc' );
    } );
} );

test( 'listReviews omits query parameters that were not supplied', function (): void {
    $factory = new HttpFactory();
    $factory->fake( [ '*' => $factory::response( [ 'reviews' => [] ], 200 ) ] );

    $client = gbpReviewsClient( $factory );

    $client->listReviews( 'accounts/1/locations/2' );

    $factory->assertSent( function ( Request $request ): bool {
        return 'https://mybusiness.googleapis.com/v4/accounts/1/locations/2/reviews' === $request->url();
    } );
} );

test( 'listReviews surfaces the nextPageToken so callers can iterate pages', function (): void {
    $factory = new HttpFactory();
    $factory->fake( [
        '*' => $factory::response(
            [
                'reviews'       => [ [ 'name' => 'accounts/1/locations/2/reviews/a', 'reviewId' => 'a' ] ],
                'nextPageToken' => 'page-2',
            ],
            200,
        ),
    ] );

    $client = gbpReviewsClient( $factory );

    $result = $client->listReviews( 'accounts/1/locations/2', pageSize: 1 );

    expect( $result->nextPageToken )->toBe( 'page-2' );
    expect( $result->hasMore() )->toBeTrue();
} );

test( 'listReviews rejects an empty parent without dispatching', function (): void {
    $factory = new HttpFactory();
    $factory->fake( [ '*' => $factory::response( [ 'reviews' => [] ], 200 ) ] );

    $client = gbpReviewsClient( $factory );

    expect( fn (): ReviewList => $client->listReviews( '' ) )
        ->toThrow( InvalidArgumentException::class );

    $factory->assertSentCount( 0 );
} );

test( 'listReviews rejects pageSize outside the documented 1..MAX_PAGE_SIZE range without making a request', function (): void {
    $factory = new HttpFactory();
    $factory->fake( [ '*' => $factory::response( [ 'reviews' => [] ], 200 ) ] );

    $client = gbpReviewsClient( $factory );

    expect( fn (): ReviewList => $client->listReviews( 'accounts/1/locations/2', pageSize: 0 ) )
        ->toThrow( InvalidArgumentException::class );

    expect( fn (): ReviewList => $client->listReviews( 'accounts/1/locations/2', pageSize: ReviewsClient::MAX_PAGE_SIZE + 1 ) )
        ->toThrow( InvalidArgumentException::class );

    $factory->assertSentCount( 0 );
} );

test( 'listReviews maps API errors to ApiException carrying the status and body', function (): void {
    $factory = new HttpFactory();
    $factory->fake( [ '*' => $factory::response( 'forbidden', 403 ) ] );

    $client = gbpReviewsClient( $factory );

    $thrown = null;

    try {
        $client->listReviews( 'accounts/1/locations/2' );
    } catch ( ApiException $exception ) {
        $thrown = $exception;
    }

    expect( $thrown )->not->toBeNull();
    expect( $thrown->statusCode() )->toBe( 403 );
    expect( $thrown->responseBody() )->toBe( 'forbidden' );
} );

test( 'listReviews treats an empty payload as a page with zero reviews', function (): void {
    $factory = new HttpFactory();
    $factory->fake( [ '*' => $factory::response( [], 200 ) ] );

    $client = gbpReviewsClient( $factory );

    $result = $client->listReviews( 'accounts/1/locations/2' );

    expect( $result->reviews )->toBe( [] );
    expect( $result->nextPageToken )->toBeNull();
} );

test( 'replyToReview PUTs the reply body against the review\'s /reply sub-resource', function (): void {
    $factory = new HttpFactory();
    $factory->fake( [
        'mybusiness.googleapis.com/v4/accounts/1/locations/2/reviews/abc/reply' => $factory::response(
            [
                'comment'    => 'Thanks for the kind words!',
                'updateTime' => '2026-08-14T18:00:00Z',
            ],
            200,
        ),
    ] );

    $client = gbpReviewsClient( $factory, new StubReviewsTokenProvider( 'the-token' ) );

    $reply = $client->replyToReview(
        'accounts/1/locations/2/reviews/abc',
        'Thanks for the kind words!',
    );

    expect( $reply )->toBeInstanceOf( ReviewReply::class );
    expect( $reply->comment )->toBe( 'Thanks for the kind words!' );
    expect( $reply->updateTime )->toBe( '2026-08-14T18:00:00Z' );

    $factory->assertSent( function ( Request $request ): bool {
        return 'PUT' === $request->method()
            && 'https://mybusiness.googleapis.com/v4/accounts/1/locations/2/reviews/abc/reply' === $request->url()
            && 'Bearer the-token' === $request->header( 'Authorization' )[0]
            && str_contains( $request->header( 'Content-Type' )[0], 'application/json' )
            && [ 'comment' => 'Thanks for the kind words!' ] === $request->data();
    } );
} );

test( 'replyToReview trims the reply body before sending', function (): void {
    $factory = new HttpFactory();
    $factory->fake( [ '*' => $factory::response( [ 'comment' => 'Thanks' ], 200 ) ] );

    $client = gbpReviewsClient( $factory );

    $client->replyToReview( 'accounts/1/locations/2/reviews/abc', "  Thanks  \n" );

    $factory->assertSent( function ( Request $request ): bool {
        return [ 'comment' => 'Thanks' ] === $request->data();
    } );
} );

test( 'replyToReview rejects an empty review name without dispatching', function (): void {
    $factory = new HttpFactory();
    $factory->fake( [ '*' => $factory::response( [ 'comment' => 'Thanks' ], 200 ) ] );

    $client = gbpReviewsClient( $factory );

    expect( fn (): ReviewReply => $client->replyToReview( '', 'Thanks' ) )
        ->toThrow( InvalidArgumentException::class );

    $factory->assertSentCount( 0 );
} );

test( 'replyToReview rejects a whitespace-only comment without dispatching', function (): void {
    $factory = new HttpFactory();
    $factory->fake( [ '*' => $factory::response( [ 'comment' => 'Thanks' ], 200 ) ] );

    $client = gbpReviewsClient( $factory );

    expect( fn (): ReviewReply => $client->replyToReview( 'accounts/1/locations/2/reviews/abc', "   \n\t" ) )
        ->toThrow( InvalidArgumentException::class );

    $factory->assertSentCount( 0 );
} );

test( 'replyToReview accepts a trimmed comment of exactly MAX_REPLY_COMMENT_BYTES bytes', function (): void {
    $factory = new HttpFactory();
    $factory->fake( [ '*' => $factory::response( [ 'comment' => 'ok' ], 200 ) ] );

    $client = gbpReviewsClient( $factory );

    $exactMax = str_repeat( 'a', ReviewsClient::MAX_REPLY_COMMENT_BYTES );

    $client->replyToReview( 'accounts/1/locations/2/reviews/abc', $exactMax );

    $factory->assertSent( function ( Request $request ) use ( $exactMax ): bool {
        return [ 'comment' => $exactMax ] === $request->data();
    } );
} );

test( 'replyToReview rejects a trimmed comment one byte over MAX_REPLY_COMMENT_BYTES without dispatching', function (): void {
    $factory = new HttpFactory();
    $factory->fake( [ '*' => $factory::response( [ 'comment' => 'ok' ], 200 ) ] );

    $client = gbpReviewsClient( $factory );

    $tooLong = str_repeat( 'a', ReviewsClient::MAX_REPLY_COMMENT_BYTES + 1 );

    expect( fn (): ReviewReply => $client->replyToReview( 'accounts/1/locations/2/reviews/abc', $tooLong ) )
        ->toThrow( InvalidArgumentException::class );

    $factory->assertSentCount( 0 );
} );

test( 'replyToReview measures the reply cap in bytes, not characters, so multi-byte UTF-8 counts correctly', function (): void {
    $factory = new HttpFactory();
    $factory->fake( [ '*' => $factory::response( [ 'comment' => 'ok' ], 200 ) ] );

    $client = gbpReviewsClient( $factory );

    // Each "€" is 3 bytes in UTF-8. 1366 * 3 = 4098 bytes — 2 bytes over the cap.
    $tooLong = str_repeat( '€', 1366 );

    expect( strlen( $tooLong ) )->toBe( 4098 );

    expect( fn (): ReviewReply => $client->replyToReview( 'accounts/1/locations/2/reviews/abc', $tooLong ) )
        ->toThrow( InvalidArgumentException::class );

    $factory->assertSentCount( 0 );
} );

test( 'replyToReview maps API errors to ApiException carrying the status and body', function (): void {
    $factory = new HttpFactory();
    $factory->fake( [ '*' => $factory::response( 'not found', 404 ) ] );

    $client = gbpReviewsClient( $factory );

    $thrown = null;

    try {
        $client->replyToReview( 'accounts/1/locations/2/reviews/abc', 'Thanks' );
    } catch ( ApiException $exception ) {
        $thrown = $exception;
    }

    expect( $thrown )->not->toBeNull();
    expect( $thrown->statusCode() )->toBe( 404 );
    expect( $thrown->responseBody() )->toBe( 'not found' );
} );
