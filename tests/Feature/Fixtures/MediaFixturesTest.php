<?php

declare( strict_types=1 );

use ArtisanPackUI\GoogleBusinessProfile\Media\DataTransferObjects\MediaItem;
use ArtisanPackUI\GoogleBusinessProfile\Media\DataTransferObjects\MediaItemDataRef;
use ArtisanPackUI\GoogleBusinessProfile\Media\MediaClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

test( 'createMediaItem maps the canonical media.create fixture into a typed DTO', function (): void {
    Http::fake( [
        'mybusiness.googleapis.com/v4/accounts/*/locations/*/media' => Http::response(
            gbpFixture( 'media/media.create.full.json' ),
            200,
        ),
    ] );

    $item = gbpFixtureClient( MediaClient::class )->createMediaItem(
        parent   : 'accounts/106237764891234567890/locations/12345678901234567890',
        mediaItem: [
            'mediaFormat'         => 'PHOTO',
            'locationAssociation' => [ 'category' => 'EXTERIOR' ],
            'sourceUrl'           => 'https://jamiescoffee.example/photos/storefront.jpg',
        ],
    );

    expect( $item )->toBeInstanceOf( MediaItem::class );
    expect( $item->name )->toBe(
        'accounts/106237764891234567890/locations/12345678901234567890/media/AF1QipMExampleMediaKey',
    );
    expect( $item->mediaFormat )->toBe( 'PHOTO' );
    expect( $item->locationAssociation )->toBe( [ 'category' => 'EXTERIOR' ] );
    expect( $item->googleUrl )->toBe( 'https://lh3.googleusercontent.example/full' );
    expect( $item->thumbnailUrl )->toBe( 'https://lh3.googleusercontent.example/thumb' );
    expect( $item->createTime )->toBe( '2026-09-06T12:34:56Z' );
    expect( $item->dimensions )->toBe( [ 'widthPixels' => 1600, 'heightPixels' => 1200 ] );
    expect( $item->insights['viewCount'] )->toBe( '4211' );
    expect( $item->attribution['profileName'] )->toBe( 'Jamie Q.' );
    expect( $item->description )->toContain( 'Storefront' );
    expect( $item->sourceUrl )->toBe( 'https://jamiescoffee.example/photos/storefront.jpg' );
    expect( $item->dataRef )->toBeInstanceOf( MediaItemDataRef::class );
    expect( $item->dataRef->resourceName )->toBe( 'CAoSLEFGMVFpcE1FeGFtcGxlUmVzb3VyY2VOYW1l' );

    Http::assertSent( function ( Request $request ): bool {
        return 'POST' === $request->method()
            && str_ends_with( $request->url(), '/media' )
            && 'PHOTO' === $request->data()['mediaFormat'];
    } );
} );

test( 'startUpload maps the media:startUpload fixture into a MediaItemDataRef', function (): void {
    Http::fake( [
        'mybusiness.googleapis.com/v4/accounts/*/locations/*/media:startUpload' => Http::response(
            gbpFixture( 'media/media.startupload.json' ),
            200,
        ),
    ] );

    $ref = gbpFixtureClient( MediaClient::class )->startUpload(
        parent: 'accounts/106237764891234567890/locations/12345678901234567890',
    );

    expect( $ref )->toBeInstanceOf( MediaItemDataRef::class );
    expect( $ref->resourceName )->toBe( 'CAoSLEFGMVFpcE1FeGFtcGxlUmVzb3VyY2VOYW1l' );
    expect( $ref->raw )->toBe( [ 'resourceName' => 'CAoSLEFGMVFpcE1FeGFtcGxlUmVzb3VyY2VOYW1l' ] );

    Http::assertSent( function ( Request $request ): bool {
        return 'POST' === $request->method()
            && str_ends_with( $request->url(), '/media:startUpload' );
    } );
} );
