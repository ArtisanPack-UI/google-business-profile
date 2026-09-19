<?php

declare( strict_types=1 );

use ArtisanPackUI\GoogleBusinessProfile\Performance\DataTransferObjects\DailyMetric;
use ArtisanPackUI\GoogleBusinessProfile\Performance\DataTransferObjects\DailyMetricTimeSeries;
use ArtisanPackUI\GoogleBusinessProfile\Performance\DataTransferObjects\MultiDailyMetricTimeSeries;
use ArtisanPackUI\GoogleBusinessProfile\Performance\PerformanceClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

test( 'getDailyMetricsTimeSeries maps the single-metric fixture into a DailyMetricTimeSeries', function (): void {
    Http::fake( [
        'businessprofileperformance.googleapis.com/v1/locations/*:getDailyMetricsTimeSeries*' => Http::response(
            gbpFixture( 'performance/daily-metrics.single.json' ),
            200,
        ),
    ] );

    $series = gbpFixtureClient( PerformanceClient::class )->getDailyMetricsTimeSeries(
        location : 'locations/12345678901234567890',
        metric   : DailyMetric::CallClicks,
        startDate: new DateTimeImmutable( '2026-09-01' ),
        endDate  : new DateTimeImmutable( '2026-09-04' ),
    );

    expect( $series )->toBeInstanceOf( DailyMetricTimeSeries::class );
    expect( $series->metric )->toBe( DailyMetric::CallClicks );
    expect( $series->datedValues )->toHaveCount( 4 );
    expect( $series->datedValues[0]->year )->toBe( 2026 );
    expect( $series->datedValues[0]->value )->toBe( 142 );
    expect( $series->datedValues[1]->value )->toBe( 0 );
    expect( $series->total() )->toBe( 142 + 0 + 319 + 217 );
    expect( $series->datedValues[0]->toDate()?->format( 'Y-m-d' ) )->toBe( '2026-09-01' );

    Http::assertSent( function ( Request $request ): bool {
        return str_contains( $request->url(), 'dailyMetric=CALL_CLICKS' )
            && str_contains( $request->url(), 'dailyRange.startDate.year=2026' )
            && str_contains( $request->url(), 'dailyRange.endDate.day=4' );
    } );
} );

test( 'fetchMultiDailyMetricsTimeSeries maps the multi-metric fixture and drops unknown metrics', function (): void {
    Http::fake( [
        'businessprofileperformance.googleapis.com/v1/locations/*:fetchMultiDailyMetricsTimeSeries*' => Http::response(
            gbpFixture( 'performance/daily-metrics.multi.json' ),
            200,
        ),
    ] );

    $multi = gbpFixtureClient( PerformanceClient::class )->fetchMultiDailyMetricsTimeSeries(
        location : 'locations/12345678901234567890',
        metrics  : [ DailyMetric::CallClicks, DailyMetric::WebsiteClicks ],
        startDate: new DateTimeImmutable( '2026-09-01' ),
        endDate  : new DateTimeImmutable( '2026-09-02' ),
    );

    expect( $multi )->toBeInstanceOf( MultiDailyMetricTimeSeries::class );
    // Unknown metric (`SOMETHING_GOOGLE_ADDED_LATER`) is filtered out; two survive.
    expect( $multi->series )->toHaveCount( 2 );

    $calls = $multi->for( DailyMetric::CallClicks );
    $web   = $multi->for( DailyMetric::WebsiteClicks );

    expect( $calls )->not->toBeNull();
    expect( $calls->total() )->toBe( 19 );
    expect( $web )->not->toBeNull();
    expect( $web->total() )->toBe( 83 );

    Http::assertSent( function ( Request $request ): bool {
        // The multi endpoint emits repeated `dailyMetrics` params without [] suffixes.
        return str_contains( $request->url(), 'dailyMetrics=CALL_CLICKS' )
            && str_contains( $request->url(), 'dailyMetrics=WEBSITE_CLICKS' );
    } );
} );
