<?php

declare( strict_types=1 );

use ArtisanPackUI\GoogleBusinessProfile\BusinessInformation\DataTransferObjects\Location;

test( 'hydrates every typed field from a full API payload', function (): void {
    $payload = [
        'name'              => 'locations/12345',
        'title'             => 'Corner Coffee — Downtown',
        'languageCode'      => 'en',
        'storeCode'         => 'STORE-001',
        'websiteUri'        => 'https://cornercoffee.example',
        'phoneNumbers'      => [
            'primaryPhone'     => '+1 555 123 4567',
            'additionalPhones' => [ '+1 555 987 6543' ],
        ],
        'categories'        => [
            'primaryCategory' => [ 'name' => 'categories/gcid:coffee_shop' ],
        ],
        'storefrontAddress' => [
            'addressLines'       => [ '1 Main St' ],
            'locality'           => 'Duluth',
            'administrativeArea' => 'MN',
            'postalCode'         => '55802',
            'regionCode'         => 'US',
        ],
        'regularHours'      => [
            'periods' => [
                [
                    'openDay'   => 'MONDAY',
                    'openTime'  => [ 'hours' => 7 ],
                    'closeDay'  => 'MONDAY',
                    'closeTime' => [ 'hours' => 18 ],
                ],
            ],
        ],
        'specialHours'      => [ 'specialHourPeriods' => [] ],
        'serviceArea'       => [ 'businessType' => 'CUSTOMER_LOCATION_ONLY' ],
        'labels'            => [ 'Featured', 'New' ],
        'latlng'            => [ 'latitude' => 46.7833, 'longitude' => -92.1066 ],
        'openInfo'          => [ 'status' => 'OPEN', 'canReopen' => true ],
        'metadata'          => [ 'mapsUri' => 'https://maps.example/1' ],
        'profile'           => [ 'description' => 'Neighborhood coffee.' ],
    ];

    $location = Location::fromArray( $payload );

    expect( $location->name )->toBe( 'locations/12345' );
    expect( $location->title )->toBe( 'Corner Coffee — Downtown' );
    expect( $location->languageCode )->toBe( 'en' );
    expect( $location->storeCode )->toBe( 'STORE-001' );
    expect( $location->websiteUri )->toBe( 'https://cornercoffee.example' );
    expect( $location->phoneNumbers )->toBe( $payload['phoneNumbers'] );
    expect( $location->categories )->toBe( $payload['categories'] );
    expect( $location->storefrontAddress )->toBe( $payload['storefrontAddress'] );
    expect( $location->regularHours )->toBe( $payload['regularHours'] );
    expect( $location->specialHours )->toBe( $payload['specialHours'] );
    expect( $location->serviceArea )->toBe( $payload['serviceArea'] );
    expect( $location->labels )->toBe( [ 'Featured', 'New' ] );
    expect( $location->latlng )->toBe( $payload['latlng'] );
    expect( $location->openInfo )->toBe( $payload['openInfo'] );
    expect( $location->metadata )->toBe( $payload['metadata'] );
    expect( $location->profile )->toBe( $payload['profile'] );
    expect( $location->raw )->toBe( $payload );
} );

test( 'coerces missing optional fields to null and required strings to empty', function (): void {
    $location = Location::fromArray( [] );

    expect( $location->name )->toBe( '' );
    expect( $location->title )->toBe( '' );
    expect( $location->languageCode )->toBeNull();
    expect( $location->storeCode )->toBeNull();
    expect( $location->websiteUri )->toBeNull();
    expect( $location->phoneNumbers )->toBeNull();
    expect( $location->categories )->toBeNull();
    expect( $location->storefrontAddress )->toBeNull();
    expect( $location->regularHours )->toBeNull();
    expect( $location->specialHours )->toBeNull();
    expect( $location->serviceArea )->toBeNull();
    expect( $location->labels )->toBeNull();
    expect( $location->latlng )->toBeNull();
    expect( $location->openInfo )->toBeNull();
    expect( $location->metadata )->toBeNull();
    expect( $location->profile )->toBeNull();
    expect( $location->raw )->toBe( [] );
} );

test( 'treats non-array sub-resource values as absent rather than propagating malformed data', function (): void {
    $location = Location::fromArray( [
        'name'              => 'locations/9',
        'title'             => 'Malformed',
        'phoneNumbers'      => 'oops',
        'storefrontAddress' => 42,
        'regularHours'      => null,
    ] );

    expect( $location->phoneNumbers )->toBeNull();
    expect( $location->storefrontAddress )->toBeNull();
    expect( $location->regularHours )->toBeNull();
} );

test( 'skips non-string entries in the labels array while keeping the string labels', function (): void {
    $location = Location::fromArray( [
        'name'   => 'locations/1',
        'title'  => 'Labeled',
        'labels' => [ 'Kept', 123, [ 'nested' ], 'AlsoKept' ],
    ] );

    expect( $location->labels )->toBe( [ 'Kept', 'AlsoKept' ] );
} );

test( 'treats a non-array labels value as absent', function (): void {
    $location = Location::fromArray( [
        'name'   => 'locations/1',
        'title'  => 'Odd',
        'labels' => 'not-an-array',
    ] );

    expect( $location->labels )->toBeNull();
} );

test( 'preserves multibyte titles without mangling non-ASCII characters', function (): void {
    $location = Location::fromArray( [
        'name'  => 'locations/1',
        'title' => '街角珈琲店 ☕️',
    ] );

    expect( $location->title )->toBe( '街角珈琲店 ☕️' );
} );

test( 'preserves unknown fields on raw so future API additions survive the DTO', function (): void {
    $payload = [
        'name'                => 'locations/9',
        'title'               => 'Future',
        'someBrandNewGoogFld' => [ 'nested' => true ],
    ];

    $location = Location::fromArray( $payload );

    expect( $location->raw )->toHaveKey( 'someBrandNewGoogFld' );
    expect( $location->raw['someBrandNewGoogFld'] )->toBe( [ 'nested' => true ] );
} );
