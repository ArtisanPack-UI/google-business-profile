<?php

declare( strict_types=1 );

use ArtisanPackUI\GoogleBusinessProfile\BusinessInformation\DataTransferObjects\Location;
use ArtisanPackUI\GoogleBusinessProfile\BusinessInformation\DataTransferObjects\LocationList;

test( 'hydrates every location entry and captures the pagination token and total size', function (): void {
    $list = LocationList::fromArray( [
        'locations'     => [
            [ 'name' => 'locations/1', 'title' => 'A' ],
            [ 'name' => 'locations/2', 'title' => 'B' ],
        ],
        'nextPageToken' => 'tok-2',
        'totalSize'     => 42,
    ] );

    expect( $list->locations )->toHaveCount( 2 );
    expect( $list->locations[0] )->toBeInstanceOf( Location::class );
    expect( $list->locations[0]->name )->toBe( 'locations/1' );
    expect( $list->locations[1]->name )->toBe( 'locations/2' );
    expect( $list->nextPageToken )->toBe( 'tok-2' );
    expect( $list->totalSize )->toBe( 42 );
    expect( $list->hasMore() )->toBeTrue();
} );

test( 'treats a response with no locations key as an empty page', function (): void {
    $list = LocationList::fromArray( [] );

    expect( $list->locations )->toBe( [] );
    expect( $list->nextPageToken )->toBeNull();
    expect( $list->totalSize )->toBeNull();
    expect( $list->hasMore() )->toBeFalse();
} );

test( 'treats an empty next page token string as no further page', function (): void {
    $list = LocationList::fromArray( [
        'locations'     => [],
        'nextPageToken' => '',
    ] );

    expect( $list->nextPageToken )->toBeNull();
    expect( $list->hasMore() )->toBeFalse();
} );

test( 'skips malformed non-array entries in the locations array', function (): void {
    $list = LocationList::fromArray( [
        'locations' => [
            [ 'name' => 'locations/1', 'title' => 'Kept' ],
            'not-an-array',
            42,
            [ 'name' => 'locations/2', 'title' => 'Also kept' ],
        ],
    ] );

    expect( $list->locations )->toHaveCount( 2 );
    expect( $list->locations[0]->name )->toBe( 'locations/1' );
    expect( $list->locations[1]->name )->toBe( 'locations/2' );
} );

test( 'treats a non-array locations value as an empty page', function (): void {
    $list = LocationList::fromArray( [ 'locations' => 'oops' ] );

    expect( $list->locations )->toBe( [] );
    expect( $list->hasMore() )->toBeFalse();
} );

test( 'parses a numeric string totalSize into an int', function (): void {
    $list = LocationList::fromArray( [
        'locations' => [],
        'totalSize' => '7',
    ] );

    expect( $list->totalSize )->toBe( 7 );
} );

test( 'treats a non-numeric totalSize as absent', function (): void {
    $list = LocationList::fromArray( [
        'locations' => [],
        'totalSize' => 'many',
    ] );

    expect( $list->totalSize )->toBeNull();
} );
