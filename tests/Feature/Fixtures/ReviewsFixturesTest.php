<?php

declare( strict_types=1 );

use ArtisanPackUI\GoogleBusinessProfile\Reviews\DataTransferObjects\Review;
use ArtisanPackUI\GoogleBusinessProfile\Reviews\DataTransferObjects\ReviewList;
use ArtisanPackUI\GoogleBusinessProfile\Reviews\DataTransferObjects\ReviewReply;
use ArtisanPackUI\GoogleBusinessProfile\Reviews\ReviewsClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

test( 'listReviews maps the canonical reviews.list fixture into typed DTOs', function (): void {
    Http::fake( [
        'mybusiness.googleapis.com/v4/accounts/*/locations/*/reviews*' => Http::response(
            gbpFixture( 'reviews/reviews.list.full.json' ),
            200,
        ),
    ] );

    $result = gbpFixtureClient( ReviewsClient::class )->listReviews(
        parent: 'accounts/106237764891234567890/locations/12345678901234567890',
    );

    expect( $result )->toBeInstanceOf( ReviewList::class );
    expect( $result->reviews )->toHaveCount( 3 );
    expect( $result->averageRating )->toBe( 4.2 );
    expect( $result->totalReviewCount )->toBe( 47 );
    expect( $result->hasMore() )->toBeTrue();
    expect( $result->nextPageToken )->toBe( 'CgcIARIDIAIoAQ' );

    [ $five, $anon, $one ] = $result->reviews;

    expect( $five )->toBeInstanceOf( Review::class );
    expect( $five->reviewId )->toBe( 'AbcReview1' );
    expect( $five->numericRating() )->toBe( 5 );
    expect( $five->comment )->toContain( 'pour-over' );
    expect( $five->reviewer->displayName )->toBe( 'Jamie Q.' );
    expect( $five->reviewer->isAnonymous )->toBeFalse();
    expect( $five->hasReply() )->toBeTrue();
    expect( $five->reviewReply->comment )->toContain( 'Thanks, Jamie' );

    expect( $anon->reviewer->isAnonymous )->toBeTrue();
    expect( $anon->reviewer->displayName )->toBeNull();
    expect( $anon->numericRating() )->toBe( 4 );
    expect( $anon->comment )->toBeNull();

    expect( $one->numericRating() )->toBe( 1 );
    expect( $one->hasReply() )->toBeFalse();

    Http::assertSent( function ( Request $request ): bool {
        return 'GET' === $request->method()
            && str_starts_with(
                $request->url(),
                'https://mybusiness.googleapis.com/v4/accounts/106237764891234567890/locations/12345678901234567890/reviews',
            );
    } );
} );

test( 'replyToReview maps the reply fixture into a ReviewReply and PUTs the trimmed comment', function (): void {
    Http::fake( [
        'mybusiness.googleapis.com/v4/accounts/*/locations/*/reviews/*/reply' => Http::response(
            gbpFixture( 'reviews/review.reply.json' ),
            200,
        ),
    ] );

    $reply = gbpFixtureClient( ReviewsClient::class )->replyToReview(
        name   : 'accounts/106237764891234567890/locations/12345678901234567890/reviews/AbcReview3',
        comment: "  Thanks for the feedback — we've extended weekend hours!  ",
    );

    expect( $reply )->toBeInstanceOf( ReviewReply::class );
    expect( $reply->comment )->toContain( 'extended weekend hours' );
    expect( $reply->updateTime )->toBe( '2026-09-06T18:30:00Z' );

    Http::assertSent( function ( Request $request ): bool {
        return 'PUT' === $request->method()
            && str_ends_with( $request->url(), '/reviews/AbcReview3/reply' )
            && "Thanks for the feedback — we've extended weekend hours!" === $request->data()['comment'];
    } );
} );
