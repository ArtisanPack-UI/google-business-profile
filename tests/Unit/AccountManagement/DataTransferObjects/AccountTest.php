<?php

declare( strict_types=1 );

use ArtisanPackUI\GoogleBusinessProfile\AccountManagement\DataTransferObjects\Account;

test( 'hydrates every documented field from a full API payload', function (): void {
    $payload = [
        'name'              => 'accounts/12345',
        'accountName'       => 'Corner Coffee',
        'type'              => 'LOCATION_GROUP',
        'role'              => 'OWNER',
        'verificationState' => 'VERIFIED',
        'vettedState'       => 'VETTED',
        'accountNumber'     => 'AC-0001',
        'permissionLevel'   => 'OWNER_LEVEL',
        'organizationInfo'  => [ 'registeredDomain' => 'example.com' ],
    ];

    $account = Account::fromArray( $payload );

    expect( $account->name )->toBe( 'accounts/12345' );
    expect( $account->accountName )->toBe( 'Corner Coffee' );
    expect( $account->type )->toBe( 'LOCATION_GROUP' );
    expect( $account->role )->toBe( 'OWNER' );
    expect( $account->verificationState )->toBe( 'VERIFIED' );
    expect( $account->vettedState )->toBe( 'VETTED' );
    expect( $account->accountNumber )->toBe( 'AC-0001' );
    expect( $account->permissionLevel )->toBe( 'OWNER_LEVEL' );
    expect( $account->raw )->toBe( $payload );
} );

test( 'coerces missing optional fields to null and required strings to empty', function (): void {
    $account = Account::fromArray( [] );

    expect( $account->name )->toBe( '' );
    expect( $account->accountName )->toBe( '' );
    expect( $account->type )->toBeNull();
    expect( $account->role )->toBeNull();
    expect( $account->verificationState )->toBeNull();
    expect( $account->vettedState )->toBeNull();
    expect( $account->accountNumber )->toBeNull();
    expect( $account->permissionLevel )->toBeNull();
    expect( $account->raw )->toBe( [] );
} );

test( 'preserves unknown fields on raw so future API additions survive the DTO', function (): void {
    $payload = [
        'name'                  => 'accounts/9',
        'accountName'           => 'Future',
        'someBrandNewGoogField' => [ 'nested' => true ],
    ];

    $account = Account::fromArray( $payload );

    expect( $account->raw )->toHaveKey( 'someBrandNewGoogField' );
    expect( $account->raw['someBrandNewGoogField'] )->toBe( [ 'nested' => true ] );
} );
