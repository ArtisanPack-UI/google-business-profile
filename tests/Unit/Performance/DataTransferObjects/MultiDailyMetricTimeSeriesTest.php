<?php

declare( strict_types=1 );

use ArtisanPackUI\GoogleBusinessProfile\Performance\DataTransferObjects\DailyMetric;
use ArtisanPackUI\GoogleBusinessProfile\Performance\DataTransferObjects\MultiDailyMetricTimeSeries;

test( 'flattens the double-nested wire shape into a single indexed list', function (): void {
    $result = MultiDailyMetricTimeSeries::fromArray( [
        'multiDailyMetricTimeSeries' => [
            [
                'dailyMetricTimeSeries' => [
                    [
                        'dailyMetric' => 'CALL_CLICKS',
                        'timeSeries'  => [
                            'datedValues' => [
                                [ 'date' => [ 'year' => 2025, 'month' => 1, 'day' => 1 ], 'value' => '2' ],
                            ],
                        ],
                    ],
                    [
                        'dailyMetric' => 'WEBSITE_CLICKS',
                        'timeSeries'  => [
                            'datedValues' => [
                                [ 'date' => [ 'year' => 2025, 'month' => 1, 'day' => 1 ], 'value' => '9' ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ] );

    expect( $result->series )->toHaveCount( 2 );
    expect( $result->series[0]->metric )->toBe( DailyMetric::CallClicks );
    expect( $result->series[1]->metric )->toBe( DailyMetric::WebsiteClicks );
    expect( $result->series[1]->datedValues[0]->value )->toBe( 9 );
} );

test( 'for returns the matching series and null when no series matches', function (): void {
    $result = MultiDailyMetricTimeSeries::fromArray( [
        'multiDailyMetricTimeSeries' => [
            [
                'dailyMetricTimeSeries' => [
                    [
                        'dailyMetric' => 'CALL_CLICKS',
                        'timeSeries'  => [ 'datedValues' => [] ],
                    ],
                ],
            ],
        ],
    ] );

    expect( $result->for( DailyMetric::CallClicks )?->metric )->toBe( DailyMetric::CallClicks );
    expect( $result->for( DailyMetric::WebsiteClicks ) )->toBeNull();
} );

test( 'for returns the first entry when a metric appears more than once', function (): void {
    $result = MultiDailyMetricTimeSeries::fromArray( [
        'multiDailyMetricTimeSeries' => [
            [
                'dailyMetricTimeSeries' => [
                    [
                        'dailyMetric' => 'CALL_CLICKS',
                        'timeSeries'  => [
                            'datedValues' => [
                                [ 'date' => [ 'year' => 2025, 'month' => 1, 'day' => 1 ], 'value' => '1' ],
                            ],
                        ],
                    ],
                    [
                        'dailyMetric' => 'CALL_CLICKS',
                        'timeSeries'  => [
                            'datedValues' => [
                                [ 'date' => [ 'year' => 2025, 'month' => 1, 'day' => 1 ], 'value' => '99' ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ] );

    $series = $result->for( DailyMetric::CallClicks );

    expect( $series )->not->toBeNull();
    expect( $series->datedValues[0]->value )->toBe( 1 );
} );

test( 'drops entries that name an unknown metric so future values do not throw', function (): void {
    $result = MultiDailyMetricTimeSeries::fromArray( [
        'multiDailyMetricTimeSeries' => [
            [
                'dailyMetricTimeSeries' => [
                    [
                        'dailyMetric' => 'CALL_CLICKS',
                        'timeSeries'  => [ 'datedValues' => [] ],
                    ],
                    [
                        'dailyMetric' => 'BUSINESS_FUTURE_METRIC',
                        'timeSeries'  => [ 'datedValues' => [] ],
                    ],
                ],
            ],
        ],
    ] );

    expect( $result->series )->toHaveCount( 1 );
    expect( $result->series[0]->metric )->toBe( DailyMetric::CallClicks );
} );

test( 'treats a response with no outer key as an empty result', function (): void {
    $result = MultiDailyMetricTimeSeries::fromArray( [] );

    expect( $result->series )->toBe( [] );
} );

test( 'tolerates non-array outer, inner, or entry shapes', function (): void {
    expect( MultiDailyMetricTimeSeries::fromArray( [ 'multiDailyMetricTimeSeries' => 'oops' ] )->series )->toBe( [] );

    $result = MultiDailyMetricTimeSeries::fromArray( [
        'multiDailyMetricTimeSeries' => [
            'not-an-array',
            [ 'dailyMetricTimeSeries' => 'oops' ],
            [
                'dailyMetricTimeSeries' => [
                    'not-an-array',
                    [ 'dailyMetric' => 'CALL_CLICKS', 'timeSeries' => [ 'datedValues' => [] ] ],
                ],
            ],
        ],
    ] );

    expect( $result->series )->toHaveCount( 1 );
    expect( $result->series[0]->metric )->toBe( DailyMetric::CallClicks );
} );
