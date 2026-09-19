<?php

declare( strict_types=1 );

use ArtisanPackUI\GoogleBusinessProfile\AccountManagement\DataTransferObjects\Account;
use ArtisanPackUI\GoogleBusinessProfile\AccountManagement\DataTransferObjects\AccountList;

test( 'hydrates every account entry and captures the pagination token', function (): void {
    $list = AccountList::fromArray( [
        'accounts'      => [
            [ 'name' => 'accounts/1', 'accountName' => 'A' ],
            [ 'name' => 'accounts/2', 'accountName' => 'B' ],
        ],
        'nextPageToken' => 'tok-2',
    ] );

    expect( $list->accounts )->toHaveCount( 2 );
    expect( $list->accounts[0] )->toBeInstanceOf( Account::class );
    expect( $list->accounts[0]->name )->toBe( 'accounts/1' );
    expect( $list->accounts[1]->name )->toBe( 'accounts/2' );
    expect( $list->nextPageToken )->toBe( 'tok-2' );
    expect( $list->hasMore() )->toBeTrue();
} );

test( 'treats a response with no accounts key as an empty page', function (): void {
    $list = AccountList::fromArray( [] );

    expect( $list->accounts )->toBe( [] );
    expect( $list->nextPageToken )->toBeNull();
    expect( $list->hasMore() )->toBeFalse();
} );

test( 'treats an empty next page token string as no further page', function (): void {
    $list = AccountList::fromArray( [
        'accounts'      => [],
        'nextPageToken' => '',
    ] );

    expect( $list->nextPageToken )->toBeNull();
    expect( $list->hasMore() )->toBeFalse();
} );

test( 'skips malformed non-array entries in the accounts array', function (): void {
    $list = AccountList::fromArray( [
        'accounts' => [
            [ 'name' => 'accounts/1', 'accountName' => 'Kept' ],
            'not-an-array',
            42,
            [ 'name' => 'accounts/2', 'accountName' => 'Also kept' ],
        ],
    ] );

    expect( $list->accounts )->toHaveCount( 2 );
    expect( $list->accounts[0]->name )->toBe( 'accounts/1' );
    expect( $list->accounts[1]->name )->toBe( 'accounts/2' );
} );

test( 'treats a non-array accounts value as an empty page', function (): void {
    $list = AccountList::fromArray( [ 'accounts' => 'oops' ] );

    expect( $list->accounts )->toBe( [] );
    expect( $list->hasMore() )->toBeFalse();
} );
