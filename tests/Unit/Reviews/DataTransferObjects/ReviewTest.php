<?php

declare( strict_types=1 );

use ArtisanPackUI\GoogleBusinessProfile\Reviews\DataTransferObjects\Review;
use ArtisanPackUI\GoogleBusinessProfile\Reviews\DataTransferObjects\Reviewer;
use ArtisanPackUI\GoogleBusinessProfile\Reviews\DataTransferObjects\ReviewReply;

test( 'hydrates every documented review field from the payload', function (): void {
    $payload = [
        'name'        => 'accounts/1/locations/2/reviews/abc',
        'reviewId'    => 'abc',
        'reviewer'    => [
            'displayName'     => 'Jamie Q.',
            'profilePhotoUrl' => 'https://example.com/photo.jpg',
            'isAnonymous'     => false,
        ],
        'starRating'  => 'FIVE',
        'comment'     => 'Great coffee!',
        'createTime'  => '2026-08-01T12:00:00Z',
        'updateTime'  => '2026-08-02T12:00:00Z',
        'reviewReply' => [
            'comment'    => 'Thanks!',
            'updateTime' => '2026-08-03T12:00:00Z',
        ],
    ];

    $review = Review::fromArray( $payload );

    expect( $review->name )->toBe( 'accounts/1/locations/2/reviews/abc' );
    expect( $review->reviewId )->toBe( 'abc' );
    expect( $review->reviewer )->toBeInstanceOf( Reviewer::class );
    expect( $review->reviewer->displayName )->toBe( 'Jamie Q.' );
    expect( $review->starRating )->toBe( 'FIVE' );
    expect( $review->comment )->toBe( 'Great coffee!' );
    expect( $review->createTime )->toBe( '2026-08-01T12:00:00Z' );
    expect( $review->updateTime )->toBe( '2026-08-02T12:00:00Z' );
    expect( $review->reviewReply )->toBeInstanceOf( ReviewReply::class );
    expect( $review->reviewReply->comment )->toBe( 'Thanks!' );
    expect( $review->hasReply() )->toBeTrue();
    expect( $review->raw )->toBe( $payload );
} );

test( 'treats a rating-only review with no comment or reply as null on those fields', function (): void {
    $review = Review::fromArray( [
        'name'       => 'accounts/1/locations/2/reviews/x',
        'reviewId'   => 'x',
        'starRating' => 'FOUR',
    ] );

    expect( $review->comment )->toBeNull();
    expect( $review->reviewer )->toBeNull();
    expect( $review->reviewReply )->toBeNull();
    expect( $review->hasReply() )->toBeFalse();
} );

test( 'skips a non-array reviewer or reviewReply sub-resource', function (): void {
    $review = Review::fromArray( [
        'name'        => 'accounts/1/locations/2/reviews/x',
        'reviewId'    => 'x',
        'reviewer'    => 'not-an-array',
        'reviewReply' => 42,
    ] );

    expect( $review->reviewer )->toBeNull();
    expect( $review->reviewReply )->toBeNull();
} );

test( 'coerces missing name and reviewId to empty strings without throwing', function (): void {
    $review = Review::fromArray( [] );

    expect( $review->name )->toBe( '' );
    expect( $review->reviewId )->toBe( '' );
} );

test( 'numericRating maps the documented starRating enum values to 1..5', function (): void {
    expect( Review::fromArray( [ 'starRating' => 'ONE' ] )->numericRating() )->toBe( 1 );
    expect( Review::fromArray( [ 'starRating' => 'TWO' ] )->numericRating() )->toBe( 2 );
    expect( Review::fromArray( [ 'starRating' => 'THREE' ] )->numericRating() )->toBe( 3 );
    expect( Review::fromArray( [ 'starRating' => 'FOUR' ] )->numericRating() )->toBe( 4 );
    expect( Review::fromArray( [ 'starRating' => 'FIVE' ] )->numericRating() )->toBe( 5 );
} );

test( 'numericRating returns 0 for null, unspecified, and unknown starRating values', function (): void {
    expect( Review::fromArray( [] )->numericRating() )->toBe( 0 );
    expect( Review::fromArray( [ 'starRating' => 'STAR_RATING_UNSPECIFIED' ] )->numericRating() )->toBe( 0 );
    expect( Review::fromArray( [ 'starRating' => 'SIX_STARS' ] )->numericRating() )->toBe( 0 );
} );

test( 'preserves unknown fields on the raw payload for forward compatibility', function (): void {
    $payload = [
        'name'          => 'accounts/1/locations/2/reviews/x',
        'reviewId'      => 'x',
        'someNewField'  => 'value',
        'nestedNewData' => [ 'k' => 'v' ],
    ];

    $review = Review::fromArray( $payload );

    expect( $review->raw )->toBe( $payload );
    expect( $review->raw['someNewField'] )->toBe( 'value' );
} );
