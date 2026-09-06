<?php

declare( strict_types=1 );

use ArtisanPackUI\GoogleBusinessProfile\Scopes\Scopes;

test( 'BUSINESS_MANAGE constant is the canonical Google Business Profile scope URL', function (): void {
    expect( Scopes::BUSINESS_MANAGE )->toBe( 'https://www.googleapis.com/auth/business.manage' );
} );

test( 'Scopes class is final and cannot be instantiated', function (): void {
    $reflection = new ReflectionClass( Scopes::class );

    expect( $reflection->isFinal() )->toBeTrue();

    $constructor = $reflection->getConstructor();

    expect( $constructor )->not->toBeNull();
    expect( $constructor->isPrivate() )->toBeTrue();
} );
