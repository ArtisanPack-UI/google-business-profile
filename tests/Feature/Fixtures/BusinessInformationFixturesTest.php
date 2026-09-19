<?php

declare( strict_types=1 );

use ArtisanPackUI\GoogleBusinessProfile\BusinessInformation\BusinessInformationClient;
use ArtisanPackUI\GoogleBusinessProfile\BusinessInformation\DataTransferObjects\Location;
use ArtisanPackUI\GoogleBusinessProfile\BusinessInformation\DataTransferObjects\LocationList;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Fixtures\FixtureTokenProvider;

test( 'listLocations maps the canonical locations.list fixture into typed DTOs', function (): void {
    Http::fake( [
        'mybusinessbusinessinformation.googleapis.com/*' => Http::response(
            gbpFixture( 'business-information/locations.list.full.json' ),
            200,
        ),
    ] );

    $result = gbpFixtureClient( BusinessInformationClient::class )->listLocations(
        parent  : 'accounts/106237764891234567890',
        readMask: [ 'name', 'title', 'storeCode' ],
    );

    expect( $result )->toBeInstanceOf( LocationList::class );
    // Fixture carries two well-formed locations plus a `{}` and a list entry;
    // both malformed rows are skipped by LocationList::fromArray.
    expect( $result->locations )->toHaveCount( 2 );
    expect( $result->totalSize )->toBe( 12 );
    expect( $result->nextPageToken )->toBe( 'CgcIARIDIAEoAQ' );

    $first = $result->locations[0];

    expect( $first )->toBeInstanceOf( Location::class );
    expect( $first->title )->toBe( "Jamie's Coffee — Pearl District" );
    expect( $first->storeCode )->toBe( 'PDX-01' );
    expect( $first->websiteUri )->toBe( 'https://jamiescoffee.example/pearl' );
    expect( $first->phoneNumbers['primaryPhone'] )->toBe( '+1 503-555-0142' );
    expect( $first->categories['primaryCategory']['displayName'] )->toBe( 'Coffee shop' );
    expect( $first->storefrontAddress['locality'] )->toBe( 'Portland' );
    expect( $first->regularHours['periods'][0]['openDay'] )->toBe( 'MONDAY' );
    expect( $first->specialHours['specialHourPeriods'][0]['closed'] )->toBeTrue();
    expect( $first->labels )->toBe( [ 'flagship', 'roastery' ] );
    expect( $first->latlng['latitude'] )->toBe( 45.5272 );
    expect( $first->openInfo['status'] )->toBe( 'OPEN' );
    expect( $first->metadata['placeId'] )->toBe( 'ChIJExampleId1234' );
    expect( $first->profile['description'] )->toContain( 'Small-batch' );
    expect( $first->raw )->toHaveKey( 'serviceItems' );

    $second = $result->locations[1];

    expect( $second->title )->toBe( "Jamie's Coffee — Mobile Cart" );
    expect( $second->storefrontAddress )->toBeNull();
    expect( $second->serviceArea['businessType'] )->toBe( 'CUSTOMER_LOCATION_ONLY' );
    expect( $second->labels )->toBe( [ 'mobile' ] );

    Http::assertSent( function ( Request $request ): bool {
        return str_starts_with(
            $request->url(),
            'https://mybusinessbusinessinformation.googleapis.com/v1/accounts/106237764891234567890/locations',
        )
            && 'Bearer ' . FixtureTokenProvider::TOKEN === $request->header( 'Authorization' )[0]
            && str_contains( $request->url(), 'readMask=name%2Ctitle%2CstoreCode' );
    } );
} );

test( 'getLocation maps a single-location fixture and drops non-string labels', function (): void {
    Http::fake( [
        'mybusinessbusinessinformation.googleapis.com/v1/locations/*' => Http::response(
            gbpFixture( 'business-information/location.get.full.json' ),
            200,
        ),
    ] );

    $location = gbpFixtureClient( BusinessInformationClient::class )->getLocation(
        'locations/12345678901234567890',
        'name,title,labels',
    );

    expect( $location )->toBeInstanceOf( Location::class );
    expect( $location->title )->toBe( "Jamie's Coffee — Pearl District" );
    // The fixture's labels array carries a non-string `42` that Location::fromArray drops.
    expect( $location->labels )->toBe( [ 'flagship', 'roastery' ] );
    expect( $location->raw['labels'] )->toBe( [ 'flagship', 42, 'roastery' ] );
} );
