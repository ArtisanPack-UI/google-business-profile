<?php

declare( strict_types=1 );

use ArtisanPackUI\GoogleBusinessProfile\Contracts\TokenProvider;
use ArtisanPackUI\GoogleBusinessProfile\Exceptions\ApiException;
use ArtisanPackUI\GoogleBusinessProfile\Http\BaseClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Request;

/**
 * Concrete BaseClient subclass used only in tests. Exposes the protected
 * request() method so we can drive it directly, and points at a stable
 * fake base URL.
 */
class FakeGbpClient extends BaseClient
{
    public function call( string $method, string $path, array $query = [], ?array $json = null ): array
    {
        return $this->request( $method, $path, $query, $json );
    }

    protected function baseUrl(): string
    {
        return 'https://mybusinessbusinessinformation.googleapis.com/v1';
    }
}

/**
 * TokenProvider that hands out a deterministic token and records how many
 * times it was asked for one. Used to prove the client fetches a fresh
 * token per request.
 */
class RecordingTokenProvider implements TokenProvider
{
    public int $callCount = 0;

    public function __construct( public string $token = 'stub-access-token' )
    {
    }

    public function accessToken(): string
    {
        $this->callCount++;

        return $this->token;
    }
}

/**
 * TokenProvider that returns a different token on each call so tests can
 * assert the second attempt used a freshly obtained credential.
 */
class RotatingTokenProvider implements TokenProvider
{
    /** @var list<string> */
    public array $issued = [];

    public function accessToken(): string
    {
        $token          = 'token-' . ( count( $this->issued ) + 1 );
        $this->issued[] = $token;

        return $token;
    }
}

function gbpMakeClient(
    HttpFactory $http,
    ?TokenProvider $provider = null,
    int $maxAttempts = 3,
): FakeGbpClient {
    return new FakeGbpClient(
        tokenProvider: $provider ?? new RecordingTokenProvider(),
        http: $http,
        timeout: 5,
        maxAttempts: $maxAttempts,
        retrySleepMs: 0,
    );
}

test( 'sends a GET with Bearer token, JSON accept header, and decoded body', function (): void {
    $factory = new HttpFactory();
    $factory->fake( [
        'mybusinessbusinessinformation.googleapis.com/v1/accounts' => $factory::response(
            [ 'accounts' => [ [ 'name' => 'accounts/1' ] ] ],
            200,
        ),
    ] );

    $provider = new RecordingTokenProvider( 'the-real-token' );
    $client   = gbpMakeClient( $factory, $provider );

    $body = $client->call( 'GET', 'accounts' );

    expect( $body )->toBe( [ 'accounts' => [ [ 'name' => 'accounts/1' ] ] ] );
    expect( $provider->callCount )->toBe( 1 );

    $factory->assertSent( function ( Request $request ): bool {
        return 'GET' === $request->method()
            && 'https://mybusinessbusinessinformation.googleapis.com/v1/accounts' === $request->url()
            && 'Bearer the-real-token' === $request->header( 'Authorization' )[0]
            && str_contains( $request->header( 'Accept' )[0], 'application/json' );
    } );
} );

test( 'joins base URL and path with a single slash regardless of surrounding slashes', function (): void {
    $factory = new HttpFactory();
    $factory->fake( [ '*' => $factory::response( [], 200 ) ] );

    $client = gbpMakeClient( $factory );
    $client->call( 'GET', '/accounts' );

    $factory->assertSent( function ( Request $request ): bool {
        return 'https://mybusinessbusinessinformation.googleapis.com/v1/accounts' === $request->url();
    } );
} );

test( 'appends query parameters when provided', function (): void {
    $factory = new HttpFactory();
    $factory->fake( [ '*' => $factory::response( [], 200 ) ] );

    $client = gbpMakeClient( $factory );
    $client->call( 'GET', 'accounts', [ 'pageSize' => 50, 'readMask' => 'name,title' ] );

    $factory->assertSent( function ( Request $request ): bool {
        $url = $request->url();

        return str_contains( $url, 'pageSize=50' )
            && str_contains( $url, 'readMask=name%2Ctitle' );
    } );
} );

test( 'sends a JSON body when provided and uses the requested verb', function (): void {
    $factory = new HttpFactory();
    $factory->fake( [ '*' => $factory::response( [ 'ok' => true ], 200 ) ] );

    $client = gbpMakeClient( $factory );
    $client->call( 'POST', 'accounts/1/locations', [], [ 'title' => 'Shop' ] );

    $factory->assertSent( function ( Request $request ): bool {
        return 'POST' === $request->method()
            && [ 'title' => 'Shop' ] === $request->data()
            && str_contains( $request->header( 'Content-Type' )[0], 'application/json' );
    } );
} );

test( 'retries 429 responses and returns the successful body on a later attempt', function (): void {
    $factory = new HttpFactory();
    $factory->fakeSequence( 'mybusinessbusinessinformation.googleapis.com/*' )
        ->push( 'slow down', 429 )
        ->push( 'slow down', 429 )
        ->push( [ 'ok' => true ], 200 );

    $provider = new RotatingTokenProvider();
    $client   = gbpMakeClient( $factory, $provider, maxAttempts: 3 );

    $body = $client->call( 'GET', 'accounts' );

    expect( $body )->toBe( [ 'ok' => true ] );
    expect( $provider->issued )->toBe( [ 'token-1', 'token-2', 'token-3' ] );
    $factory->assertSentCount( 3 );
} );

test( 'retries 5xx responses and eventually surfaces an ApiException carrying the last status', function (): void {
    $factory = new HttpFactory();
    $factory->fake( [ '*' => $factory::response( 'server oops', 503 ) ] );

    $client = gbpMakeClient( $factory, maxAttempts: 3 );

    $thrown = null;

    try {
        $client->call( 'GET', 'accounts' );
    } catch ( ApiException $exception ) {
        $thrown = $exception;
    }

    expect( $thrown )->not->toBeNull();
    expect( $thrown->statusCode() )->toBe( 503 );
    expect( $thrown->responseBody() )->toBe( 'server oops' );
    $factory->assertSentCount( 3 );
} );

test( 'does not retry 4xx client errors other than 429', function (): void {
    $factory = new HttpFactory();
    $factory->fake( [ '*' => $factory::response( 'nope', 404 ) ] );

    $client = gbpMakeClient( $factory, maxAttempts: 3 );

    $thrown = null;

    try {
        $client->call( 'GET', 'accounts/missing' );
    } catch ( ApiException $exception ) {
        $thrown = $exception;
    }

    expect( $thrown )->not->toBeNull();
    expect( $thrown->statusCode() )->toBe( 404 );
    $factory->assertSentCount( 1 );
} );

test( 'maps ConnectionException to a transport-failure ApiException after retries exhausted', function (): void {
    $factory = new HttpFactory();
    $factory->fake( function (): void {
        throw new ConnectionException( 'network unreachable' );
    } );

    $provider = new RecordingTokenProvider();
    $client   = gbpMakeClient( $factory, $provider, maxAttempts: 2 );

    $thrown = null;

    try {
        $client->call( 'GET', 'accounts' );
    } catch ( ApiException $exception ) {
        $thrown = $exception;
    }

    expect( $thrown )->not->toBeNull();
    expect( $thrown->statusCode() )->toBe( 0 );
    expect( $thrown->getPrevious() )->toBeInstanceOf( ConnectionException::class );
    // Every attempt fetches a fresh token, so the provider call count is the attempt count.
    expect( $provider->callCount )->toBe( 2 );
} );

test( 'returns an empty array when the successful response has no JSON body', function (): void {
    $factory = new HttpFactory();
    $factory->fake( [ '*' => $factory::response( '', 204 ) ] );

    $client = gbpMakeClient( $factory );

    expect( $client->call( 'DELETE', 'accounts/1/locations/9' ) )->toBe( [] );
} );

