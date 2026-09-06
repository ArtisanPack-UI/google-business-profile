<?php

declare( strict_types=1 );

use ArtisanPackUI\GoogleBusinessProfile\Exceptions\ApiException;
use ArtisanPackUI\GoogleBusinessProfile\Exceptions\GoogleBusinessProfileException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;

function gbpResponse( int $status, string $body = '' ): Illuminate\Http\Client\Response
{
    $factory = new HttpFactory();
    $factory->fake( [
        'https://example.test/*' => $factory::response( $body, $status ),
    ] );

    return $factory->get( 'https://example.test/anything' );
}

test( 'fromResponse captures status code and raw body', function (): void {
    $response = gbpResponse( 500, '{"error":"boom"}' );

    $exception = ApiException::fromResponse( $response );

    expect( $exception )->toBeInstanceOf( GoogleBusinessProfileException::class );
    expect( $exception->statusCode() )->toBe( 500 );
    expect( $exception->responseBody() )->toBe( '{"error":"boom"}' );
    expect( $exception->getCode() )->toBe( 500 );
} );

test( 'fromResponse uses a specific message for 401 auth failures', function (): void {
    $exception = ApiException::fromResponse( gbpResponse( 401, 'nope' ) );

    expect( $exception->getMessage() )->toContain( 'authentication failed' );
    expect( $exception->getMessage() )->toContain( '401' );
} );

test( 'fromResponse uses a specific message for 403 scope failures', function (): void {
    $exception = ApiException::fromResponse( gbpResponse( 403, 'nope' ) );

    expect( $exception->getMessage() )->toContain( 'authorization failed' );
    expect( $exception->getMessage() )->toContain( '403' );
} );

test( 'fromResponse uses a specific message for 404 not-found', function (): void {
    $exception = ApiException::fromResponse( gbpResponse( 404 ) );

    expect( $exception->getMessage() )->toContain( 'not found' );
} );

test( 'fromResponse uses a specific message for 429 rate-limit', function (): void {
    $exception = ApiException::fromResponse( gbpResponse( 429 ) );

    expect( $exception->getMessage() )->toContain( 'rate-limited' );
} );

test( 'fromResponse detects the access-approval flavor of 429 (quota_limit_value 0)', function (): void {
    $body = json_encode( [
        'error' => [
            'code'    => 429,
            'message' => 'Quota exceeded…',
            'status'  => 'RESOURCE_EXHAUSTED',
            'details' => [
                [
                    '@type'  => 'type.googleapis.com/google.rpc.ErrorInfo',
                    'reason' => 'RATE_LIMIT_EXCEEDED',
                    'domain' => 'googleapis.com',
                    'metadata' => [
                        'quota_limit'       => 'DefaultRequestsPerMinutePerProject',
                        'quota_limit_value' => '0',
                        'service'           => 'mybusinessaccountmanagement.googleapis.com',
                    ],
                ],
            ],
        ],
    ] );

    $exception = ApiException::fromResponse( gbpResponse( 429, $body ) );

    expect( $exception->getMessage() )->toContain( 'access is not approved' );
    expect( $exception->getMessage() )->toContain( 'https://support.google.com/business/contact/api_default' );
    expect( $exception->statusCode() )->toBe( 429 );
    expect( $exception->responseBody() )->toBe( $body );
} );

test( 'fromResponse treats a 429 with a non-zero quota_limit_value as ordinary rate-limiting', function (): void {
    $body = json_encode( [
        'error' => [
            'details' => [
                [
                    'reason'   => 'RATE_LIMIT_EXCEEDED',
                    'metadata' => [ 'quota_limit_value' => '100' ],
                ],
            ],
        ],
    ] );

    $exception = ApiException::fromResponse( gbpResponse( 429, $body ) );

    expect( $exception->getMessage() )->toContain( 'rate-limited' );
    expect( $exception->getMessage() )->not->toContain( 'access is not approved' );
} );

test( 'fromResponse falls back to the generic 429 message on malformed bodies', function ( mixed $body ): void {
    $exception = ApiException::fromResponse( gbpResponse( 429, (string) $body ) );

    expect( $exception->getMessage() )->toContain( 'rate-limited' );
    expect( $exception->getMessage() )->not->toContain( 'access is not approved' );
} )->with( [
    'empty body'                => '',
    'not JSON'                  => 'not json at all',
    'JSON but not an object'    => '[1,2,3]',
    'missing error key'         => '{"foo":"bar"}',
    'details not an array'      => '{"error":{"details":"oops"}}',
    'detail not an array'       => '{"error":{"details":["oops"]}}',
    'wrong reason'              => '{"error":{"details":[{"reason":"OTHER","metadata":{"quota_limit_value":"0"}}]}}',
    'metadata not an array'     => '{"error":{"details":[{"reason":"RATE_LIMIT_EXCEEDED","metadata":"nope"}]}}',
    'no quota_limit_value key'  => '{"error":{"details":[{"reason":"RATE_LIMIT_EXCEEDED","metadata":{}}]}}',
    'quota_limit_value is array' => '{"error":{"details":[{"reason":"RATE_LIMIT_EXCEEDED","metadata":{"quota_limit_value":["0"]}}]}}',
    'quota_limit_value is null' => '{"error":{"details":[{"reason":"RATE_LIMIT_EXCEEDED","metadata":{"quota_limit_value":null}}]}}',
] );

test( 'fromResponse formats generic 5xx server errors with their status', function (): void {
    $exception = ApiException::fromResponse( gbpResponse( 502 ) );

    expect( $exception->getMessage() )->toContain( 'server error' );
    expect( $exception->getMessage() )->toContain( '502' );
} );

test( 'transportFailure wraps the previous exception and reports status 0', function (): void {
    $previous = new ConnectionException( 'DNS lookup failed' );

    $exception = ApiException::transportFailure( $previous );

    expect( $exception )->toBeInstanceOf( GoogleBusinessProfileException::class );
    expect( $exception->statusCode() )->toBe( 0 );
    expect( $exception->responseBody() )->toBeNull();
    expect( $exception->getPrevious() )->toBe( $previous );
    expect( $exception->getMessage() )->toContain( 'DNS lookup failed' );
} );
