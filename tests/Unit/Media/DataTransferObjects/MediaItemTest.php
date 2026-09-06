<?php

declare( strict_types=1 );

use ArtisanPackUI\GoogleBusinessProfile\Media\DataTransferObjects\MediaItem;
use ArtisanPackUI\GoogleBusinessProfile\Media\DataTransferObjects\MediaItemDataRef;

test( 'hydrates every documented media item field from the payload', function (): void {
    $payload = [
        'name'                => 'accounts/1/locations/2/media/abc',
        'mediaFormat'         => 'PHOTO',
        'locationAssociation' => [ 'category' => 'COVER' ],
        'googleUrl'           => 'https://lh3.googleusercontent.com/abc',
        'thumbnailUrl'        => 'https://lh3.googleusercontent.com/abc=s100',
        'createTime'          => '2026-08-01T12:00:00Z',
        'dimensions'          => [ 'widthPixels' => 1600, 'heightPixels' => 1200 ],
        'insights'            => [ 'viewCount' => '42' ],
        'attribution'         => [
            'profileName'     => 'Jamie Q.',
            'profilePhotoUrl' => 'https://example.test/photo.jpg',
        ],
        'description'         => 'Front of store',
        'sourceUrl'           => 'https://cdn.example.test/front.jpg',
    ];

    $item = MediaItem::fromArray( $payload );

    expect( $item->name )->toBe( 'accounts/1/locations/2/media/abc' );
    expect( $item->mediaFormat )->toBe( 'PHOTO' );
    expect( $item->locationAssociation )->toBe( [ 'category' => 'COVER' ] );
    expect( $item->googleUrl )->toBe( 'https://lh3.googleusercontent.com/abc' );
    expect( $item->thumbnailUrl )->toBe( 'https://lh3.googleusercontent.com/abc=s100' );
    expect( $item->createTime )->toBe( '2026-08-01T12:00:00Z' );
    expect( $item->dimensions )->toBe( [ 'widthPixels' => 1600, 'heightPixels' => 1200 ] );
    expect( $item->insights )->toBe( [ 'viewCount' => '42' ] );
    expect( $item->attribution['profileName'] )->toBe( 'Jamie Q.' );
    expect( $item->description )->toBe( 'Front of store' );
    expect( $item->sourceUrl )->toBe( 'https://cdn.example.test/front.jpg' );
    expect( $item->dataRef )->toBeNull();
    expect( $item->raw )->toBe( $payload );
} );

test( 'hydrates the dataRef sub-resource when present', function (): void {
    $item = MediaItem::fromArray( [
        'name'    => 'accounts/1/locations/2/media/xyz',
        'dataRef' => [ 'resourceName' => 'CAISABC123' ],
    ] );

    expect( $item->dataRef )->toBeInstanceOf( MediaItemDataRef::class );
    expect( $item->dataRef->resourceName )->toBe( 'CAISABC123' );
} );

test( 'coerces missing fields to null and preserves unknown fields on the raw payload', function (): void {
    $item = MediaItem::fromArray( [
        'name'     => 'accounts/1/locations/2/media/x',
        'weirdKey' => 'preserved',
    ] );

    expect( $item->name )->toBe( 'accounts/1/locations/2/media/x' );
    expect( $item->mediaFormat )->toBeNull();
    expect( $item->locationAssociation )->toBeNull();
    expect( $item->dimensions )->toBeNull();
    expect( $item->insights )->toBeNull();
    expect( $item->attribution )->toBeNull();
    expect( $item->description )->toBeNull();
    expect( $item->sourceUrl )->toBeNull();
    expect( $item->dataRef )->toBeNull();
    expect( $item->raw['weirdKey'] )->toBe( 'preserved' );
} );

test( 'treats a missing name as an empty string rather than throwing', function (): void {
    $item = MediaItem::fromArray( [] );

    expect( $item->name )->toBe( '' );
} );

test( 'ignores object-shaped fields when the wire value is a list', function (): void {
    $item = MediaItem::fromArray( [
        'name'                => 'accounts/1/locations/2/media/x',
        'locationAssociation' => [ 'a', 'b' ],
        'dimensions'          => [ 'a', 'b' ],
    ] );

    expect( $item->locationAssociation )->toBeNull();
    expect( $item->dimensions )->toBeNull();
} );

test( 'treats an empty-array field as the object it claims to be', function (): void {
    $item = MediaItem::fromArray( [
        'name'                => 'accounts/1/locations/2/media/x',
        'locationAssociation' => [],
    ] );

    expect( $item->locationAssociation )->toBe( [] );
} );
