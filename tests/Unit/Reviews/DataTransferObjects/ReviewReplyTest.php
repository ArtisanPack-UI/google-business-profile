<?php

declare( strict_types=1 );

use ArtisanPackUI\GoogleBusinessProfile\Reviews\DataTransferObjects\ReviewReply;

test( 'hydrates the reply comment and update time from the payload', function (): void {
    $reply = ReviewReply::fromArray( [
        'comment'    => 'Thanks for the kind words!',
        'updateTime' => '2026-08-14T18:00:00Z',
    ] );

    expect( $reply->comment )->toBe( 'Thanks for the kind words!' );
    expect( $reply->updateTime )->toBe( '2026-08-14T18:00:00Z' );
} );

test( 'defaults missing comment to an empty string rather than throwing', function (): void {
    $reply = ReviewReply::fromArray( [ 'updateTime' => '2026-08-14T18:00:00Z' ] );

    expect( $reply->comment )->toBe( '' );
    expect( $reply->updateTime )->toBe( '2026-08-14T18:00:00Z' );
} );

test( 'treats an empty-string update time as absent', function (): void {
    $reply = ReviewReply::fromArray( [
        'comment'    => 'Thanks',
        'updateTime' => '',
    ] );

    expect( $reply->updateTime )->toBeNull();
} );

test( 'hydrates the reviewReplyState and policyViolation moderation fields when present', function (): void {
    $reply = ReviewReply::fromArray( [
        'comment'          => 'Thanks',
        'reviewReplyState' => 'REJECTED',
        'policyViolation'  => 'OFFENSIVE',
    ] );

    expect( $reply->reviewReplyState )->toBe( 'REJECTED' );
    expect( $reply->policyViolation )->toBe( 'OFFENSIVE' );
} );

test( 'defaults reviewReplyState and policyViolation to null when absent or empty', function (): void {
    $absent = ReviewReply::fromArray( [ 'comment' => 'Thanks' ] );

    expect( $absent->reviewReplyState )->toBeNull();
    expect( $absent->policyViolation )->toBeNull();

    $empty = ReviewReply::fromArray( [
        'comment'          => 'Thanks',
        'reviewReplyState' => '',
        'policyViolation'  => '',
    ] );

    expect( $empty->reviewReplyState )->toBeNull();
    expect( $empty->policyViolation )->toBeNull();
} );

test( 'preserves an approved-state payload without a policyViolation', function (): void {
    $reply = ReviewReply::fromArray( [
        'comment'          => 'Thanks',
        'reviewReplyState' => 'APPROVED',
    ] );

    expect( $reply->reviewReplyState )->toBe( 'APPROVED' );
    expect( $reply->policyViolation )->toBeNull();
} );
