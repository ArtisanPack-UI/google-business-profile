<?php

declare( strict_types=1 );

use ArtisanPackUI\GoogleBusinessProfile\BusinessInformation\BusinessInformationClient;
use ArtisanPackUI\GoogleBusinessProfile\BusinessInformation\DataTransferObjects\Location;
use ArtisanPackUI\GoogleBusinessProfile\BusinessInformation\DataTransferObjects\LocationList;
use ArtisanPackUI\GoogleBusinessProfile\Contracts\TokenProvider;
use ArtisanPackUI\GoogleBusinessProfile\Exceptions\ApiException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Request;

class StubBusinessInformationTokenProvider implements TokenProvider
{
    public function __construct( public string $token = 'stub-business-token' )
    {
    }

    public function accessToken(): string
    {
        return $this->token;
    }
}

function gbpBusinessInformationClient(
    HttpFactory $http,
    ?TokenProvider $provider = null,
    int $maxAttempts = 1,
): BusinessInformationClient {
    return new BusinessInformationClient(
        tokenProvider: $provider ?? new StubBusinessInformationTokenProvider(),
        http: $http,
        timeout: 5,
        maxAttempts: $maxAttempts,
        retrySleepMs: 0,
    );
}

test( 'listLocations hits the Business Information host with a Bearer token and returns typed DTOs', function (): void {
    $factory = new HttpFactory();
    $factory->fake( [
        'mybusinessbusinessinformation.googleapis.com/v1/accounts/1/locations*' => $factory::response(
            [
                'locations'     => [
                    [
                        'name'      => 'locations/10',
                        'title'     => 'Downtown',
                        'storeCode' => 'DT-1',
                    ],
                    [
                        'name'  => 'locations/11',
                        'title' => 'Uptown',
                    ],
                ],
                'totalSize'     => 2,
            ],
            200,
        ),
    ] );

    $client = gbpBusinessInformationClient( $factory, new StubBusinessInformationTokenProvider( 'the-token' ) );

    $result = $client->listLocations( 'accounts/1', 'name,title,storeCode' );

    expect( $result )->toBeInstanceOf( LocationList::class );
    expect( $result->locations )->toHaveCount( 2 );
    expect( $result->locations[0]->name )->toBe( 'locations/10' );
    expect( $result->locations[0]->title )->toBe( 'Downtown' );
    expect( $result->locations[0]->storeCode )->toBe( 'DT-1' );
    expect( $result->locations[1]->name )->toBe( 'locations/11' );
    expect( $result->totalSize )->toBe( 2 );
    expect( $result->nextPageToken )->toBeNull();
    expect( $result->hasMore() )->toBeFalse();

    $factory->assertSent( function ( Request $request ): bool {
        return 'GET' === $request->method()
            && str_starts_with( $request->url(), 'https://mybusinessbusinessinformation.googleapis.com/v1/accounts/1/locations' )
            && 'Bearer the-token' === $request->header( 'Authorization' )[0]
            && str_contains( $request->header( 'Accept' )[0], 'application/json' )
            && str_contains( $request->url(), 'readMask=name%2Ctitle%2CstoreCode' );
    } );
} );

test( 'listLocations appends pageSize, pageToken, filter, and orderBy when provided', function (): void {
    $factory = new HttpFactory();
    $factory->fake( [ '*' => $factory::response( [ 'locations' => [] ], 200 ) ] );

    $client = gbpBusinessInformationClient( $factory );

    $client->listLocations(
        parent   : 'accounts/42',
        readMask : 'name',
        pageSize : 25,
        pageToken: 'next-page',
        filter   : 'title=Coffee*',
        orderBy  : 'title desc',
    );

    $factory->assertSent( function ( Request $request ): bool {
        $url = $request->url();

        return str_contains( $url, 'pageSize=25' )
            && str_contains( $url, 'pageToken=next-page' )
            && str_contains( $url, 'filter=title%3DCoffee%2A' )
            && str_contains( $url, 'orderBy=title%20desc' );
    } );
} );

test( 'listLocations normalises an array readMask to a comma-separated wire value', function (): void {
    $factory = new HttpFactory();
    $factory->fake( [ '*' => $factory::response( [ 'locations' => [] ], 200 ) ] );

    $client = gbpBusinessInformationClient( $factory );

    $client->listLocations( 'accounts/1', [ 'name', 'title', 'phoneNumbers' ] );

    $factory->assertSent( function ( Request $request ): bool {
        return str_contains( $request->url(), 'readMask=name%2Ctitle%2CphoneNumbers' );
    } );
} );

test( 'listLocations strips empty segments and whitespace from a comma-delimited readMask string', function (): void {
    $factory = new HttpFactory();
    $factory->fake( [ '*' => $factory::response( [ 'locations' => [] ], 200 ) ] );

    $client = gbpBusinessInformationClient( $factory );

    $client->listLocations( 'accounts/1', ' name , , title,,phoneNumbers ' );

    $factory->assertSent( function ( Request $request ): bool {
        return str_contains( $request->url(), 'readMask=name%2Ctitle%2CphoneNumbers' );
    } );
} );

test( 'listLocations surfaces the nextPageToken so callers can iterate pages', function (): void {
    $factory = new HttpFactory();
    $factory->fake( [
        '*' => $factory::response(
            [
                'locations'     => [ [ 'name' => 'locations/1', 'title' => 'A' ] ],
                'nextPageToken' => 'page-2',
            ],
            200,
        ),
    ] );

    $client = gbpBusinessInformationClient( $factory );

    $result = $client->listLocations( 'accounts/1', 'name', pageSize: 1 );

    expect( $result->nextPageToken )->toBe( 'page-2' );
    expect( $result->hasMore() )->toBeTrue();
} );

test( 'listLocations rejects pageSize outside the documented 1..MAX_PAGE_SIZE range without making a request', function (): void {
    $factory = new HttpFactory();
    $factory->fake( [ '*' => $factory::response( [ 'locations' => [] ], 200 ) ] );

    $client = gbpBusinessInformationClient( $factory );

    expect( fn (): LocationList => $client->listLocations( 'accounts/1', 'name', pageSize: 0 ) )
        ->toThrow( InvalidArgumentException::class );

    expect( fn (): LocationList => $client->listLocations( 'accounts/1', 'name', pageSize: BusinessInformationClient::MAX_PAGE_SIZE + 1 ) )
        ->toThrow( InvalidArgumentException::class );

    $factory->assertSentCount( 0 );
} );

test( 'listLocations rejects an empty parent without making a request', function (): void {
    $factory = new HttpFactory();
    $factory->fake( [ '*' => $factory::response( [ 'locations' => [] ], 200 ) ] );

    $client = gbpBusinessInformationClient( $factory );

    expect( fn (): LocationList => $client->listLocations( '', 'name' ) )
        ->toThrow( InvalidArgumentException::class );

    $factory->assertSentCount( 0 );
} );

test( 'listLocations rejects an empty readMask without making a request', function (): void {
    $factory = new HttpFactory();
    $factory->fake( [ '*' => $factory::response( [ 'locations' => [] ], 200 ) ] );

    $client = gbpBusinessInformationClient( $factory );

    expect( fn (): LocationList => $client->listLocations( 'accounts/1', '' ) )
        ->toThrow( InvalidArgumentException::class );

    expect( fn (): LocationList => $client->listLocations( 'accounts/1', [] ) )
        ->toThrow( InvalidArgumentException::class );

    expect( fn (): LocationList => $client->listLocations( 'accounts/1', [ '', '  ' ] ) )
        ->toThrow( InvalidArgumentException::class );

    expect( fn (): LocationList => $client->listLocations( 'accounts/1', ',,,' ) )
        ->toThrow( InvalidArgumentException::class );

    $factory->assertSentCount( 0 );
} );

test( 'listLocations maps API errors to ApiException carrying the status and body', function (): void {
    $factory = new HttpFactory();
    $factory->fake( [ '*' => $factory::response( 'forbidden', 403 ) ] );

    $client = gbpBusinessInformationClient( $factory );

    $thrown = null;

    try {
        $client->listLocations( 'accounts/1', 'name' );
    } catch ( ApiException $exception ) {
        $thrown = $exception;
    }

    expect( $thrown )->not->toBeNull();
    expect( $thrown->statusCode() )->toBe( 403 );
    expect( $thrown->responseBody() )->toBe( 'forbidden' );
} );

test( 'getLocation fetches a single location and returns a typed DTO', function (): void {
    $factory = new HttpFactory();
    $factory->fake( [
        'mybusinessbusinessinformation.googleapis.com/v1/locations/12345*' => $factory::response(
            [
                'name'         => 'locations/12345',
                'title'        => 'Corner Coffee',
                'websiteUri'   => 'https://corner.example',
                'phoneNumbers' => [ 'primaryPhone' => '+1 555 000 0000' ],
            ],
            200,
        ),
    ] );

    $client = gbpBusinessInformationClient( $factory );

    $location = $client->getLocation( 'locations/12345', [ 'name', 'title', 'websiteUri', 'phoneNumbers' ] );

    expect( $location )->toBeInstanceOf( Location::class );
    expect( $location->name )->toBe( 'locations/12345' );
    expect( $location->title )->toBe( 'Corner Coffee' );
    expect( $location->websiteUri )->toBe( 'https://corner.example' );
    expect( $location->phoneNumbers )->toBe( [ 'primaryPhone' => '+1 555 000 0000' ] );

    $factory->assertSent( function ( Request $request ): bool {
        return 'GET' === $request->method()
            && str_starts_with( $request->url(), 'https://mybusinessbusinessinformation.googleapis.com/v1/locations/12345' )
            && str_contains( $request->url(), 'readMask=name%2Ctitle%2CwebsiteUri%2CphoneNumbers' );
    } );
} );

test( 'getLocation rejects an empty name without making a request', function (): void {
    $factory = new HttpFactory();
    $factory->fake( [ '*' => $factory::response( [ 'name' => 'locations/1' ], 200 ) ] );

    $client = gbpBusinessInformationClient( $factory );

    expect( fn (): Location => $client->getLocation( '', 'name' ) )
        ->toThrow( InvalidArgumentException::class );

    $factory->assertSentCount( 0 );
} );

test( 'getLocation rejects a readMask that normalises to empty', function (): void {
    $factory = new HttpFactory();
    $factory->fake( [ '*' => $factory::response( [ 'name' => 'locations/1' ], 200 ) ] );

    $client = gbpBusinessInformationClient( $factory );

    expect( fn (): Location => $client->getLocation( 'locations/1', '   ' ) )
        ->toThrow( InvalidArgumentException::class );

    expect( fn (): Location => $client->getLocation( 'locations/1', ',,,' ) )
        ->toThrow( InvalidArgumentException::class );

    expect( fn (): Location => $client->getLocation( 'locations/1', [] ) )
        ->toThrow( InvalidArgumentException::class );

    $factory->assertSentCount( 0 );
} );

test( 'patchLocation sends a PATCH with the update mask, JSON body, and injected name', function (): void {
    $factory = new HttpFactory();
    $factory->fake( [
        'mybusinessbusinessinformation.googleapis.com/v1/locations/12345*' => $factory::response(
            [
                'name'         => 'locations/12345',
                'title'        => 'Corner Coffee',
                'websiteUri'   => 'https://corner-updated.example',
                'phoneNumbers' => [ 'primaryPhone' => '+1 555 111 2222' ],
            ],
            200,
        ),
    ] );

    $client = gbpBusinessInformationClient( $factory );

    $update = [
        'websiteUri'   => 'https://corner-updated.example',
        'phoneNumbers' => [ 'primaryPhone' => '+1 555 111 2222' ],
    ];

    $location = $client->patchLocation(
        name      : 'locations/12345',
        location  : $update,
        updateMask: [ 'websiteUri', 'phoneNumbers' ],
    );

    expect( $location )->toBeInstanceOf( Location::class );
    expect( $location->websiteUri )->toBe( 'https://corner-updated.example' );
    expect( $location->phoneNumbers )->toBe( [ 'primaryPhone' => '+1 555 111 2222' ] );

    $factory->assertSent( function ( Request $request ) use ( $update ): bool {
        $expectedName = 'locations/12345';

        return 'PATCH' === $request->method()
            && str_starts_with( $request->url(), 'https://mybusinessbusinessinformation.googleapis.com/v1/locations/12345' )
            && str_contains( $request->url(), 'updateMask=websiteUri%2CphoneNumbers' )
            && ! str_contains( $request->url(), 'validateOnly' )
            && $request->data() === array_merge( $update, [ 'name' => $expectedName ] );
    } );
} );

test( 'patchLocation forwards validateOnly=true and returns null on the empty dry-run response', function (): void {
    $factory = new HttpFactory();
    $factory->fake( [ '*' => $factory::response( '', 200 ) ] );

    $client = gbpBusinessInformationClient( $factory );

    $result = $client->patchLocation(
        name        : 'locations/1',
        location    : [ 'title' => 'New' ],
        updateMask  : 'title',
        validateOnly: true,
    );

    expect( $result )->toBeNull();

    $factory->assertSent( function ( Request $request ): bool {
        return str_contains( $request->url(), 'validateOnly=true' );
    } );
} );

test( 'patchLocation still hydrates a Location when a dry-run response unexpectedly includes a body', function (): void {
    $factory = new HttpFactory();
    $factory->fake( [ '*' => $factory::response( [ 'name' => 'locations/1', 'title' => 'Preview' ], 200 ) ] );

    $client = gbpBusinessInformationClient( $factory );

    $result = $client->patchLocation(
        name        : 'locations/1',
        location    : [ 'title' => 'Preview' ],
        updateMask  : 'title',
        validateOnly: true,
    );

    expect( $result )->toBeInstanceOf( Location::class );
    expect( $result->title )->toBe( 'Preview' );
} );

test( 'patchLocation rejects an empty name, body, or updateMask without making a request', function (): void {
    $factory = new HttpFactory();
    $factory->fake( [ '*' => $factory::response( [ 'name' => 'locations/1' ], 200 ) ] );

    $client = gbpBusinessInformationClient( $factory );

    expect( fn (): Location => $client->patchLocation( '', [ 'title' => 'x' ], 'title' ) )
        ->toThrow( InvalidArgumentException::class );

    expect( fn (): Location => $client->patchLocation( 'locations/1', [], 'title' ) )
        ->toThrow( InvalidArgumentException::class );

    expect( fn (): Location => $client->patchLocation( 'locations/1', [ 'title' => 'x' ], '' ) )
        ->toThrow( InvalidArgumentException::class );

    expect( fn (): Location => $client->patchLocation( 'locations/1', [ 'title' => 'x' ], [] ) )
        ->toThrow( InvalidArgumentException::class );

    $factory->assertSentCount( 0 );
} );

test( 'patchLocation maps API errors to ApiException carrying the status and body', function (): void {
    $factory = new HttpFactory();
    $factory->fake( [ '*' => $factory::response( 'invalid', 400 ) ] );

    $client = gbpBusinessInformationClient( $factory );

    $thrown = null;

    try {
        $client->patchLocation( 'locations/1', [ 'title' => 'x' ], 'title' );
    } catch ( ApiException $exception ) {
        $thrown = $exception;
    }

    expect( $thrown )->not->toBeNull();
    expect( $thrown->statusCode() )->toBe( 400 );
    expect( $thrown->responseBody() )->toBe( 'invalid' );
} );

test( 'patchLocation overwrites a caller-supplied name in the body so URL and body agree', function (): void {
    $factory = new HttpFactory();
    $factory->fake( [ '*' => $factory::response( [ 'name' => 'locations/canonical', 'title' => 'T' ], 200 ) ] );

    $client = gbpBusinessInformationClient( $factory );

    $client->patchLocation(
        name      : 'locations/canonical',
        location  : [ 'name' => 'locations/from-body', 'title' => 'New' ],
        updateMask: 'title',
    );

    $factory->assertSent( function ( Request $request ): bool {
        return str_starts_with( $request->url(), 'https://mybusinessbusinessinformation.googleapis.com/v1/locations/canonical' )
            && [ 'name' => 'locations/canonical', 'title' => 'New' ] === $request->data();
    } );
} );

test( 'getLocation maps API errors to ApiException carrying the status and body', function (): void {
    $factory = new HttpFactory();
    $factory->fake( [ '*' => $factory::response( 'not found', 404 ) ] );

    $client = gbpBusinessInformationClient( $factory );

    $thrown = null;

    try {
        $client->getLocation( 'locations/does-not-exist', 'name' );
    } catch ( ApiException $exception ) {
        $thrown = $exception;
    }

    expect( $thrown )->not->toBeNull();
    expect( $thrown->statusCode() )->toBe( 404 );
    expect( $thrown->responseBody() )->toBe( 'not found' );
} );
