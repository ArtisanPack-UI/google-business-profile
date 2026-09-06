<?php

declare( strict_types=1 );

use ArtisanPackUI\GoogleBusinessProfile\GoogleBusinessProfile;
use ArtisanPackUI\GoogleBusinessProfile\GoogleBusinessProfileServiceProvider;

test( 'the service provider binds the google-business-profile singleton', function (): void {
    expect( app( 'google-business-profile' ) )->toBeInstanceOf( GoogleBusinessProfile::class );
    expect( app( 'google-business-profile' ) )->toBe( app( 'google-business-profile' ) );
} );

test( 'boot degrades gracefully when the artisanpack-ui/google package is not installed', function (): void {
    // The test suite runs without artisanpack-ui/google as a dev dependency,
    // so the class_exists() guard in boot() takes the no-op branch. The
    // fact that every test in the suite already resolves the provider
    // proves boot() does not error under that condition; this test locks
    // that guarantee in explicitly.
    expect( class_exists( \ArtisanPackUI\Google\Facades\Google::class ) )->toBeFalse();

    $provider = new GoogleBusinessProfileServiceProvider( app() );

    $provider->boot();

    expect( true )->toBeTrue();
} );

