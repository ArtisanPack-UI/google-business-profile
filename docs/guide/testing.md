---
title: Testing with Http::fake()
---

# Testing with `Http::fake()`

The shared `BaseClient` accepts an `Illuminate\Http\Client\Factory`
instance in its constructor. The service provider wires it to the same
singleton the `Http` facade resolves, so `Http::fake()` works against
every client with no bespoke test doubles.

## Faking a single family

Match on the family's host name and return whatever payload the client
DTOs expect:

```php
use ArtisanPackUI\GoogleBusinessProfile\AccountManagement\AccountManagementClient;
use Illuminate\Support\Facades\Http;

Http::fake( [
    'mybusinessaccountmanagement.googleapis.com/*' => Http::response( [
        'accounts' => [
            [
                'name'        => 'accounts/123',
                'accountName' => 'Test Business',
                'type'        => 'PERSONAL',
            ],
        ],
    ] ),
] );

$accounts = app( AccountManagementClient::class )->listAccounts();

expect( $accounts->accounts )->toHaveCount( 1 );
expect( $accounts->accounts[0]->name )->toBe( 'accounts/123' );
```

## Faking all four families at once

Each family has its own host name, so a single `Http::fake()` call can
cover the whole surface:

```php
Http::fake( [
    'mybusinessaccountmanagement.googleapis.com/*'    => Http::response( [ /* … */ ] ),
    'mybusinessbusinessinformation.googleapis.com/*'  => Http::response( [ /* … */ ] ),
    'mybusiness.googleapis.com/*'                     => Http::response( [ /* … */ ] ),
    'businessprofileperformance.googleapis.com/*'     => Http::response( [ /* … */ ] ),
] );
```

## Testing retry behaviour

`BaseClient` retries transport failures (`ConnectionException`), `HTTP 429`,
and `HTTP 5xx` responses up to its configured attempt count (default 3).
`Http::fake()` accepts a response sequence, which is enough to exercise
the retry loop end-to-end:

```php
Http::fake( [
    'mybusinessaccountmanagement.googleapis.com/*' => Http::sequence()
        ->push( [ 'error' => [ 'code' => 503 ] ], 503 )
        ->push( [ 'error' => [ 'code' => 503 ] ], 503 )
        ->push( [ 'accounts' => [] ], 200 ),
] );

$accounts = app( AccountManagementClient::class )->listAccounts();

// The two 503s are retried transparently; the caller sees the 200.
expect( $accounts->accounts )->toBeEmpty();
```

Retryable statuses are `429` and any `5xx`. `4xx` responses are surfaced
immediately — retrying them would not change the outcome.

## Speeding up retry sleeps

`BaseClient` sleeps `retrySleepMs` milliseconds between attempts (default
`250`). In tests you can construct a client with `retrySleepMs: 0` to
skip the sleep entirely, or rebind the service in your `TestCase`:

```php
$this->app->bind( AccountManagementClient::class, function ( $app ) {
    return new AccountManagementClient(
        tokenProvider: $app->make( TokenProvider::class ),
        http:          $app->make( \Illuminate\Http\Client\Factory::class ),
        retrySleepMs:  0,
    );
} );
```

## Asserting the request that went out

Because the client uses the same factory as the `Http` facade,
`Http::assertSent()` works directly:

```php
Http::assertSent( function ( $request ) {
    return $request->hasHeader( 'Authorization', 'Bearer test-token' )
        && $request->url() === 'https://mybusinessaccountmanagement.googleapis.com/v1/accounts';
} );
```

## Testing error mapping

Any non-2xx response — after retries are exhausted — is mapped to
`ApiException::fromResponse()`. A `ConnectionException` on the final
attempt is mapped to `ApiException::transportFailure()`.

```php
use ArtisanPackUI\GoogleBusinessProfile\Exceptions\ApiException;

Http::fake( [
    'mybusinessaccountmanagement.googleapis.com/*' => Http::response(
        [ 'error' => [ 'code' => 403, 'message' => 'Permission denied' ] ],
        403,
    ),
] );

expect( fn () => app( AccountManagementClient::class )->listAccounts() )
    ->toThrow( ApiException::class );
```

Both factories preserve the status code and raw response body on the
exception, so tests can assert on either.

## Stubbing the `TokenProvider`

Pair `Http::fake()` with a stub `TokenProvider` binding so no real token
is ever required. See the [token-provider guide](token-provider.md) for
a stub example.

See also: [[token-provider]] for wiring the provider that ships tokens
into these tests.
