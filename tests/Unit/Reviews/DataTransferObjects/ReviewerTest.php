<?php

declare( strict_types=1 );

use ArtisanPackUI\GoogleBusinessProfile\Reviews\DataTransferObjects\Reviewer;

test( 'hydrates every documented reviewer field from the payload', function (): void {
    $reviewer = Reviewer::fromArray( [
        'displayName'     => 'Jamie Q.',
        'profilePhotoUrl' => 'https://example.com/photo.jpg',
        'isAnonymous'     => false,
    ] );

    expect( $reviewer->displayName )->toBe( 'Jamie Q.' );
    expect( $reviewer->profilePhotoUrl )->toBe( 'https://example.com/photo.jpg' );
    expect( $reviewer->isAnonymous )->toBeFalse();
} );

test( 'treats an anonymous reviewer with missing name and photo as null', function (): void {
    $reviewer = Reviewer::fromArray( [ 'isAnonymous' => true ] );

    expect( $reviewer->displayName )->toBeNull();
    expect( $reviewer->profilePhotoUrl )->toBeNull();
    expect( $reviewer->isAnonymous )->toBeTrue();
} );

test( 'treats empty-string reviewer name and photo as absent', function (): void {
    $reviewer = Reviewer::fromArray( [
        'displayName'     => '',
        'profilePhotoUrl' => '',
    ] );

    expect( $reviewer->displayName )->toBeNull();
    expect( $reviewer->profilePhotoUrl )->toBeNull();
    expect( $reviewer->isAnonymous )->toBeFalse();
} );

test( 'defaults isAnonymous to false when omitted', function (): void {
    $reviewer = Reviewer::fromArray( [] );

    expect( $reviewer->isAnonymous )->toBeFalse();
} );
