<?php

declare( strict_types=1 );

use ArtisanPackUI\GoogleBusinessProfile\Performance\DataTransferObjects\DailyMetric;
use ArtisanPackUI\GoogleBusinessProfile\Performance\DataTransferObjects\DailyMetricTimeSeries;

test( 'fromSingleResponse carries the caller-supplied metric and hydrates every dated value', function (): void {
    $series = DailyMetricTimeSeries::fromSingleResponse(
        DailyMetric::CallClicks,
        [
            'timeSeries' => [
                'datedValues' => [
                    [ 'date' => [ 'year' => 2025, 'month' => 1, 'day' => 1 ], 'value' => '3' ],
                    [ 'date' => [ 'year' => 2025, 'month' => 1, 'day' => 2 ], 'value' => '7' ],
                ],
            ],
        ],
    );

    expect( $series->metric )->toBe( DailyMetric::CallClicks );
    expect( $series->datedValues )->toHaveCount( 2 );
    expect( $series->datedValues[0]->value )->toBe( 3 );
    expect( $series->datedValues[1]->day )->toBe( 2 );
} );

test( 'fromSingleResponse treats a missing timeSeries as an empty series without throwing', function (): void {
    $series = DailyMetricTimeSeries::fromSingleResponse( DailyMetric::WebsiteClicks, [] );

    expect( $series->metric )->toBe( DailyMetric::WebsiteClicks );
    expect( $series->datedValues )->toBe( [] );
} );

test( 'fromSingleResponse tolerates a non-array timeSeries payload', function (): void {
    $series = DailyMetricTimeSeries::fromSingleResponse( DailyMetric::WebsiteClicks, [ 'timeSeries' => 'oops' ] );

    expect( $series->datedValues )->toBe( [] );
} );

test( 'fromSingleResponse skips non-array entries in the datedValues list', function (): void {
    $series = DailyMetricTimeSeries::fromSingleResponse(
        DailyMetric::CallClicks,
        [
            'timeSeries' => [
                'datedValues' => [
                    [ 'date' => [ 'year' => 2025, 'month' => 1, 'day' => 1 ], 'value' => '1' ],
                    'not-an-array',
                    42,
                    [ 'date' => [ 'year' => 2025, 'month' => 1, 'day' => 2 ], 'value' => '2' ],
                ],
            ],
        ],
    );

    expect( $series->datedValues )->toHaveCount( 2 );
    expect( $series->datedValues[0]->value )->toBe( 1 );
    expect( $series->datedValues[1]->value )->toBe( 2 );
} );

test( 'fromMultiEntry hydrates the echoed dailyMetric and its time series', function (): void {
    $series = DailyMetricTimeSeries::fromMultiEntry( [
        'dailyMetric' => 'BUSINESS_DIRECTION_REQUESTS',
        'timeSeries'  => [
            'datedValues' => [
                [ 'date' => [ 'year' => 2025, 'month' => 5, 'day' => 1 ], 'value' => '11' ],
            ],
        ],
    ] );

    expect( $series )->not->toBeNull();
    expect( $series->metric )->toBe( DailyMetric::BusinessDirectionRequests );
    expect( $series->datedValues )->toHaveCount( 1 );
    expect( $series->datedValues[0]->value )->toBe( 11 );
} );

test( 'fromMultiEntry returns null when the entry names an unknown metric string', function (): void {
    $series = DailyMetricTimeSeries::fromMultiEntry( [
        'dailyMetric' => 'BUSINESS_FUTURE_METRIC',
        'timeSeries'  => [ 'datedValues' => [] ],
    ] );

    expect( $series )->toBeNull();
} );

test( 'fromMultiEntry returns null when the dailyMetric key is absent or the wrong type', function (): void {
    expect( DailyMetricTimeSeries::fromMultiEntry( [ 'timeSeries' => [] ] ) )->toBeNull();
    expect( DailyMetricTimeSeries::fromMultiEntry( [ 'dailyMetric' => 42, 'timeSeries' => [] ] ) )->toBeNull();
} );

test( 'total sums every day in the series and returns 0 for an empty series', function (): void {
    $series = DailyMetricTimeSeries::fromSingleResponse(
        DailyMetric::CallClicks,
        [
            'timeSeries' => [
                'datedValues' => [
                    [ 'date' => [ 'year' => 2025, 'month' => 1, 'day' => 1 ], 'value' => '3' ],
                    [ 'date' => [ 'year' => 2025, 'month' => 1, 'day' => 2 ], 'value' => '7' ],
                    [ 'date' => [ 'year' => 2025, 'month' => 1, 'day' => 3 ], 'value' => '12' ],
                ],
            ],
        ],
    );

    expect( $series->total() )->toBe( 22 );
    expect( DailyMetricTimeSeries::fromSingleResponse( DailyMetric::CallClicks, [] )->total() )->toBe( 0 );
} );
