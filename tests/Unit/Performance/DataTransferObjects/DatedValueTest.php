<?php

declare( strict_types=1 );

use ArtisanPackUI\GoogleBusinessProfile\Performance\DataTransferObjects\DatedValue;

test( 'hydrates year, month, day and value from a well-formed payload', function (): void {
    $value = DatedValue::fromArray( [
        'date'  => [ 'year' => 2025, 'month' => 3, 'day' => 14 ],
        'value' => '123',
    ] );

    expect( $value->year )->toBe( 2025 );
    expect( $value->month )->toBe( 3 );
    expect( $value->day )->toBe( 14 );
    expect( $value->value )->toBe( 123 );
} );

test( 'coerces a numeric int value alongside the wire string form', function (): void {
    $value = DatedValue::fromArray( [
        'date'  => [ 'year' => 2025, 'month' => 1, 'day' => 1 ],
        'value' => 42,
    ] );

    expect( $value->value )->toBe( 42 );
} );

test( 'defaults a missing value to zero rather than throwing', function (): void {
    $value = DatedValue::fromArray( [
        'date' => [ 'year' => 2025, 'month' => 1, 'day' => 1 ],
    ] );

    expect( $value->value )->toBe( 0 );
} );

test( 'defaults a non-numeric value to zero', function (): void {
    $value = DatedValue::fromArray( [
        'date'  => [ 'year' => 2025, 'month' => 1, 'day' => 1 ],
        'value' => 'many',
    ] );

    expect( $value->value )->toBe( 0 );
} );

test( 'defaults missing date components to zero', function (): void {
    $value = DatedValue::fromArray( [ 'value' => 5 ] );

    expect( $value->year )->toBe( 0 );
    expect( $value->month )->toBe( 0 );
    expect( $value->day )->toBe( 0 );
    expect( $value->value )->toBe( 5 );
} );

test( 'toDate returns a UTC midnight DateTimeImmutable for a well-formed payload', function (): void {
    $value = DatedValue::fromArray( [
        'date'  => [ 'year' => 2025, 'month' => 3, 'day' => 14 ],
        'value' => '1',
    ] );

    $date = $value->toDate();

    expect( $date )->not->toBeNull();
    expect( $date->format( 'Y-m-d H:i:s' ) )->toBe( '2025-03-14 00:00:00' );
    expect( $date->getTimezone()->getName() )->toBe( 'UTC' );
} );

test( 'toDate returns null when any date component is missing', function (): void {
    $value = DatedValue::fromArray( [ 'value' => 1 ] );

    expect( $value->toDate() )->toBeNull();
} );

test( 'toDate tolerates a non-array date field', function (): void {
    $value = DatedValue::fromArray( [ 'date' => 'oops', 'value' => 1 ] );

    expect( $value->year )->toBe( 0 );
    expect( $value->toDate() )->toBeNull();
} );
