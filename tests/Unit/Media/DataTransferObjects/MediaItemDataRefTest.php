<?php

declare( strict_types=1 );

use ArtisanPackUI\GoogleBusinessProfile\Media\DataTransferObjects\MediaItemDataRef;

test( 'hydrates resourceName and preserves the raw payload', function (): void {
    $dataRef = MediaItemDataRef::fromArray( [
        'resourceName' => 'CAISABC123',
        'extraField'   => 'preserved',
    ] );

    expect( $dataRef->resourceName )->toBe( 'CAISABC123' );
    expect( $dataRef->raw['extraField'] )->toBe( 'preserved' );
} );

test( 'coerces a missing resourceName to an empty string rather than throwing', function (): void {
    $dataRef = MediaItemDataRef::fromArray( [] );

    expect( $dataRef->resourceName )->toBe( '' );
    expect( $dataRef->raw )->toBe( [] );
} );

test( 'casts a non-string resourceName to a string for wire safety', function (): void {
    $dataRef = MediaItemDataRef::fromArray( [ 'resourceName' => 12345 ] );

    expect( $dataRef->resourceName )->toBe( '12345' );
} );
