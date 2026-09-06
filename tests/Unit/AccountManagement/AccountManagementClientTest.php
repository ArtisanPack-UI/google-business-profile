<?php

declare( strict_types=1 );

use ArtisanPackUI\GoogleBusinessProfile\AccountManagement\AccountManagementClient;
use ArtisanPackUI\GoogleBusinessProfile\AccountManagement\DataTransferObjects\AccountList;
use ArtisanPackUI\GoogleBusinessProfile\Contracts\TokenProvider;
use ArtisanPackUI\GoogleBusinessProfile\Exceptions\ApiException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Request;

class StubAccountTokenProvider implements TokenProvider
{
    public function __construct( public string $token = 'stub-account-token' )
    {
    }

    public function accessToken(): string
    {
        return $this->token;
    }
}

function gbpAccountManagementClient(
    HttpFactory $http,
    ?TokenProvider $provider = null,
    int $maxAttempts = 1,
): AccountManagementClient {
    return new AccountManagementClient(
        tokenProvider: $provider ?? new StubAccountTokenProvider(),
        http: $http,
        timeout: 5,
        maxAttempts: $maxAttempts,
        retrySleepMs: 0,
    );
}

test( 'listAccounts hits the Account Management host with a Bearer token and returns typed DTOs', function (): void {
    $factory = new HttpFactory();
    $factory->fake( [
        'mybusinessaccountmanagement.googleapis.com/v1/accounts*' => $factory::response(
            [
                'accounts' => [
                    [
                        'name'              => 'accounts/1',
                        'accountName'       => 'Corner Coffee',
                        'type'              => 'LOCATION_GROUP',
                        'role'              => 'OWNER',
                        'verificationState' => 'VERIFIED',
                    ],
                    [
                        'name'              => 'accounts/2',
                        'accountName'       => 'Second Shop',
                        'type'              => 'PERSONAL',
                        'role'              => 'MANAGER',
                        'verificationState' => 'UNVERIFIED',
                    ],
                ],
            ],
            200,
        ),
    ] );

    $client = gbpAccountManagementClient( $factory, new StubAccountTokenProvider( 'the-token' ) );

    $result = $client->listAccounts();

    expect( $result )->toBeInstanceOf( AccountList::class );
    expect( $result->accounts )->toHaveCount( 2 );
    expect( $result->accounts[0]->name )->toBe( 'accounts/1' );
    expect( $result->accounts[0]->accountName )->toBe( 'Corner Coffee' );
    expect( $result->accounts[0]->role )->toBe( 'OWNER' );
    expect( $result->accounts[1]->verificationState )->toBe( 'UNVERIFIED' );
    expect( $result->nextPageToken )->toBeNull();
    expect( $result->hasMore() )->toBeFalse();

    $factory->assertSent( function ( Request $request ): bool {
        return 'GET' === $request->method()
            && str_starts_with( $request->url(), 'https://mybusinessaccountmanagement.googleapis.com/v1/accounts' )
            && 'Bearer the-token' === $request->header( 'Authorization' )[0]
            && str_contains( $request->header( 'Accept' )[0], 'application/json' );
    } );
} );

test( 'listAccounts appends pageSize, pageToken, filter, and parentAccount when provided', function (): void {
    $factory = new HttpFactory();
    $factory->fake( [ '*' => $factory::response( [ 'accounts' => [] ], 200 ) ] );

    $client = gbpAccountManagementClient( $factory );

    $client->listAccounts(
        pageSize     : 10,
        pageToken    : 'next-page',
        filter       : 'type=LOCATION_GROUP',
        parentAccount: 'accounts/999',
    );

    $factory->assertSent( function ( Request $request ): bool {
        $url = $request->url();

        return str_contains( $url, 'pageSize=10' )
            && str_contains( $url, 'pageToken=next-page' )
            && str_contains( $url, 'filter=type%3DLOCATION_GROUP' )
            && str_contains( $url, 'parentAccount=accounts%2F999' );
    } );
} );

test( 'listAccounts omits query parameters that were not supplied', function (): void {
    $factory = new HttpFactory();
    $factory->fake( [ '*' => $factory::response( [ 'accounts' => [] ], 200 ) ] );

    $client = gbpAccountManagementClient( $factory );

    $client->listAccounts();

    $factory->assertSent( function ( Request $request ): bool {
        return 'https://mybusinessaccountmanagement.googleapis.com/v1/accounts' === $request->url();
    } );
} );

test( 'listAccounts surfaces the nextPageToken so callers can iterate pages', function (): void {
    $factory = new HttpFactory();
    $factory->fake( [
        '*' => $factory::response(
            [
                'accounts'      => [ [ 'name' => 'accounts/1', 'accountName' => 'A' ] ],
                'nextPageToken' => 'page-2',
            ],
            200,
        ),
    ] );

    $client = gbpAccountManagementClient( $factory );

    $result = $client->listAccounts( pageSize: 1 );

    expect( $result->nextPageToken )->toBe( 'page-2' );
    expect( $result->hasMore() )->toBeTrue();
} );

test( 'listAccounts rejects pageSize outside the documented 1..MAX_PAGE_SIZE range without making a request', function (): void {
    $factory = new HttpFactory();
    $factory->fake( [ '*' => $factory::response( [ 'accounts' => [] ], 200 ) ] );

    $client = gbpAccountManagementClient( $factory );

    expect( fn (): AccountList => $client->listAccounts( pageSize: 0 ) )
        ->toThrow( InvalidArgumentException::class );

    expect( fn (): AccountList => $client->listAccounts( pageSize: AccountManagementClient::MAX_PAGE_SIZE + 1 ) )
        ->toThrow( InvalidArgumentException::class );

    $factory->assertSentCount( 0 );
} );

test( 'listAccounts maps API errors to ApiException carrying the status and body', function (): void {
    $factory = new HttpFactory();
    $factory->fake( [ '*' => $factory::response( 'forbidden', 403 ) ] );

    $client = gbpAccountManagementClient( $factory );

    $thrown = null;

    try {
        $client->listAccounts();
    } catch ( ApiException $exception ) {
        $thrown = $exception;
    }

    expect( $thrown )->not->toBeNull();
    expect( $thrown->statusCode() )->toBe( 403 );
    expect( $thrown->responseBody() )->toBe( 'forbidden' );
} );

test( 'listAccounts treats an empty payload as a page with zero accounts', function (): void {
    $factory = new HttpFactory();
    $factory->fake( [ '*' => $factory::response( [], 200 ) ] );

    $client = gbpAccountManagementClient( $factory );

    $result = $client->listAccounts();

    expect( $result->accounts )->toBe( [] );
    expect( $result->nextPageToken )->toBeNull();
} );
