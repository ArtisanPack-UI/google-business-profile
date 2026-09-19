<?php

declare( strict_types=1 );

use ArtisanPackUI\GoogleBusinessProfile\AccountManagement\AccountManagementClient;
use ArtisanPackUI\GoogleBusinessProfile\AccountManagement\DataTransferObjects\Account;
use ArtisanPackUI\GoogleBusinessProfile\AccountManagement\DataTransferObjects\AccountList;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Fixtures\FixtureTokenProvider;

test( 'listAccounts maps the canonical accounts.list fixture into typed DTOs', function (): void {
    Http::fake( [
        'mybusinessaccountmanagement.googleapis.com/v1/accounts*' => Http::response(
            gbpFixture( 'account-management/accounts.list.full.json' ),
            200,
        ),
    ] );

    $result = gbpFixtureClient( AccountManagementClient::class )->listAccounts( pageSize: 10 );

    expect( $result )->toBeInstanceOf( AccountList::class );
    expect( $result->accounts )->toHaveCount( 3 );
    expect( $result->hasMore() )->toBeTrue();
    expect( $result->nextPageToken )->toBe( 'CgkKB29mZnNldC0y' );

    $first = $result->accounts[0];

    expect( $first )->toBeInstanceOf( Account::class );
    expect( $first->name )->toBe( 'accounts/106237764891234567890' );
    expect( $first->accountName )->toBe( "Jamie's Coffee Roasters" );
    expect( $first->type )->toBe( 'PERSONAL' );
    expect( $first->role )->toBe( 'OWNER' );
    expect( $first->verificationState )->toBe( 'VERIFIED' );
    expect( $first->vettedState )->toBe( 'VETTED' );
    expect( $first->accountNumber )->toBe( 'A-000123' );
    expect( $first->permissionLevel )->toBe( 'OWNER_LEVEL' );
    expect( $first->raw )->toHaveKey( 'organizationInfo' );
    expect( $first->raw['organizationInfo']['registeredDomain'] )->toBe( 'jamiescoffee.example' );

    $third = $result->accounts[2];

    expect( $third->accountName )->toBe( 'Field Team' );
    expect( $third->type )->toBe( 'USER_GROUP' );
    expect( $third->verificationState )->toBeNull();
    expect( $third->permissionLevel )->toBeNull();

    Http::assertSent( function ( Request $request ): bool {
        return str_starts_with( $request->url(), 'https://mybusinessaccountmanagement.googleapis.com/v1/accounts' )
            && 'Bearer ' . FixtureTokenProvider::TOKEN === $request->header( 'Authorization' )[0];
    } );
} );

test( 'listAccounts hydrates an empty page from the empty fixture', function (): void {
    Http::fake( [
        'mybusinessaccountmanagement.googleapis.com/*' => Http::response(
            gbpFixture( 'account-management/accounts.list.empty.json' ),
            200,
        ),
    ] );

    $result = gbpFixtureClient( AccountManagementClient::class )->listAccounts();

    expect( $result->accounts )->toBe( [] );
    expect( $result->nextPageToken )->toBeNull();
    expect( $result->hasMore() )->toBeFalse();
} );
