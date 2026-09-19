<?php

declare( strict_types=1 );

use ArtisanPackUI\GoogleBusinessProfile\LocalPosts\DataTransferObjects\LocalPost;
use ArtisanPackUI\GoogleBusinessProfile\LocalPosts\DataTransferObjects\LocalPostList;
use ArtisanPackUI\GoogleBusinessProfile\LocalPosts\LocalPostsClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

test( 'listLocalPosts maps the canonical localPosts.list fixture into typed DTOs', function (): void {
    Http::fake( [
        'mybusiness.googleapis.com/v4/accounts/*/locations/*/localPosts*' => Http::response(
            gbpFixture( 'local-posts/local-posts.list.full.json' ),
            200,
        ),
    ] );

    $result = gbpFixtureClient( LocalPostsClient::class )->listLocalPosts(
        parent: 'accounts/106237764891234567890/locations/12345678901234567890',
    );

    expect( $result )->toBeInstanceOf( LocalPostList::class );
    // 4 valid objects + 1 malformed list entry (dropped) in the fixture.
    expect( $result->localPosts )->toHaveCount( 4 );
    expect( $result->nextPageToken )->toBe( 'Cg0KCwoJdG9waWMtb2Zm' );

    [ $standard, $event, $offer, $alert ] = $result->localPosts;

    expect( $standard )->toBeInstanceOf( LocalPost::class );
    expect( $standard->topicType )->toBe( 'STANDARD' );
    expect( $standard->summary )->toContain( 'autumn menu' );
    expect( $standard->hasCallToAction() )->toBeTrue();
    expect( $standard->callToAction->actionType )->toBe( 'LEARN_MORE' );
    expect( $standard->callToAction->url )->toBe( 'https://jamiescoffee.example/autumn' );
    expect( $standard->hasMedia() )->toBeTrue();
    expect( $standard->media[0]->mediaFormat )->toBe( 'PHOTO' );

    expect( $event->topicType )->toBe( 'EVENT' );
    expect( $event->event )->not->toBeNull();
    expect( $event->event->title )->toBe( 'Latte Art Throwdown' );
    expect( $event->event->startDate )->toBe( [ 'year' => 2026, 'month' => 9, 'day' => 20 ] );
    expect( $event->event->endTime )->toBe( [ 'hours' => 22, 'minutes' => 30 ] );
    expect( $event->offer )->toBeNull();

    expect( $offer->topicType )->toBe( 'OFFER' );
    expect( $offer->offer )->not->toBeNull();
    expect( $offer->offer->couponCode )->toBe( 'POUR20' );
    expect( $offer->offer->redeemOnlineUrl )->toBe( 'https://jamiescoffee.example/redeem' );
    expect( $offer->offer->termsConditions )->toContain( 'One redemption' );
    expect( $offer->event->title )->toBe( '20% Off Pour-Overs' );

    expect( $alert->topicType )->toBe( 'ALERT' );
    expect( $alert->alertType )->toBe( 'COVID_19' );
    expect( $alert->state )->toBe( 'PROCESSING' );
    // Non-array `media` value must be normalised to an empty list, not throw.
    expect( $alert->media )->toBe( [] );

    Http::assertSent( function ( Request $request ): bool {
        return 'GET' === $request->method()
            && str_starts_with(
                $request->url(),
                'https://mybusiness.googleapis.com/v4/accounts/106237764891234567890/locations/12345678901234567890/localPosts',
            );
    } );
} );

test( 'createLocalPost maps the event-post create fixture and posts to the location path', function (): void {
    Http::fake( [
        'mybusiness.googleapis.com/v4/accounts/*/locations/*/localPosts' => Http::response(
            gbpFixture( 'local-posts/local-post.create.event.json' ),
            200,
        ),
    ] );

    $post = gbpFixtureClient( LocalPostsClient::class )->createLocalPost(
        parent: 'accounts/106237764891234567890/locations/12345678901234567890',
        post  : [
            'languageCode' => 'en',
            'summary'      => 'Grand reopening.',
            'topicType'    => 'EVENT',
        ],
    );

    expect( $post )->toBeInstanceOf( LocalPost::class );
    expect( $post->topicType )->toBe( 'EVENT' );
    expect( $post->event->title )->toBe( 'Grand Reopening' );
    expect( $post->event->startTime )->toBe( [ 'hours' => 8 ] );
    expect( $post->callToAction->url )->toBe( 'https://jamiescoffee.example/reopen' );
    expect( $post->media[0]->googleUrl )->toContain( 'lh3.googleusercontent' );

    Http::assertSent( function ( Request $request ): bool {
        return 'POST' === $request->method()
            && str_ends_with( $request->url(), '/localPosts' )
            && 'EVENT' === $request->data()['topicType'];
    } );
} );
