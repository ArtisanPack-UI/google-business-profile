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
