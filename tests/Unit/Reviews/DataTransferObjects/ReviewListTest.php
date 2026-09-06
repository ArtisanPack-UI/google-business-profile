<?php

declare( strict_types=1 );

use ArtisanPackUI\GoogleBusinessProfile\Reviews\DataTransferObjects\Review;
use ArtisanPackUI\GoogleBusinessProfile\Reviews\DataTransferObjects\ReviewList;

test( 'hydrates every review entry and captures aggregates plus the pagination token', function (): void {
    $list = ReviewList::fromArray( [
        'reviews'          => [
            [ 'name' => 'accounts/1/locations/2/reviews/a', 'reviewId' => 'a', 'starRating' => 'FIVE' ],
            [ 'name' => 'accounts/1/locations/2/reviews/b', 'reviewId' => 'b', 'starRating' => 'FOUR' ],
        ],
        'averageRating'    => 4.5,
        'totalReviewCount' => 42,
        'nextPageToken'    => 'tok-2',
    ] );

    expect( $list->reviews )->toHaveCount( 2 );
    expect( $list->reviews[0] )->toBeInstanceOf( Review::class );
    expect( $list->reviews[0]->reviewId )->toBe( 'a' );
    expect( $list->reviews[1]->reviewId )->toBe( 'b' );
    expect( $list->averageRating )->toBe( 4.5 );
    expect( $list->totalReviewCount )->toBe( 42 );
    expect( $list->nextPageToken )->toBe( 'tok-2' );
    expect( $list->hasMore() )->toBeTrue();
} );

test( 'treats a response with no reviews key as an empty page', function (): void {
    $list = ReviewList::fromArray( [] );

    expect( $list->reviews )->toBe( [] );
    expect( $list->averageRating )->toBeNull();
    expect( $list->totalReviewCount )->toBeNull();
    expect( $list->nextPageToken )->toBeNull();
    expect( $list->hasMore() )->toBeFalse();
} );

test( 'treats an empty next page token string as no further page', function (): void {
    $list = ReviewList::fromArray( [
        'reviews'       => [],
        'nextPageToken' => '',
    ] );

    expect( $list->nextPageToken )->toBeNull();
    expect( $list->hasMore() )->toBeFalse();
} );

test( 'skips malformed non-array entries in the reviews array', function (): void {
    $list = ReviewList::fromArray( [
        'reviews' => [
            [ 'name' => 'accounts/1/locations/2/reviews/kept-1', 'reviewId' => 'kept-1' ],
            'not-an-array',
            42,
            [ 'name' => 'accounts/1/locations/2/reviews/kept-2', 'reviewId' => 'kept-2' ],
        ],
    ] );

    expect( $list->reviews )->toHaveCount( 2 );
    expect( $list->reviews[0]->reviewId )->toBe( 'kept-1' );
    expect( $list->reviews[1]->reviewId )->toBe( 'kept-2' );
} );

test( 'treats a non-array reviews value as an empty page', function (): void {
    $list = ReviewList::fromArray( [ 'reviews' => 'oops' ] );

    expect( $list->reviews )->toBe( [] );
    expect( $list->hasMore() )->toBeFalse();
} );

test( 'coerces averageRating to float and totalReviewCount to int', function (): void {
    $list = ReviewList::fromArray( [
        'reviews'          => [],
        'averageRating'    => '3.75',
        'totalReviewCount' => '10',
    ] );

    expect( $list->averageRating )->toBe( 3.75 );
    expect( $list->totalReviewCount )->toBe( 10 );
} );

test( 'ignores non-numeric aggregate values rather than crashing', function (): void {
    $list = ReviewList::fromArray( [
        'reviews'          => [],
        'averageRating'    => 'oops',
        'totalReviewCount' => [ 'wrong' ],
    ] );

    expect( $list->averageRating )->toBeNull();
    expect( $list->totalReviewCount )->toBeNull();
} );
