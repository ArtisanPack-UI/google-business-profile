<?php

declare( strict_types=1 );

use ArtisanPackUI\GoogleBusinessProfile\Contracts\TokenProvider;
use ArtisanPackUI\GoogleBusinessProfile\Exceptions\ApiException;
use ArtisanPackUI\GoogleBusinessProfile\Performance\DataTransferObjects\DailyMetric;
use ArtisanPackUI\GoogleBusinessProfile\Performance\DataTransferObjects\DailyMetricTimeSeries;
use ArtisanPackUI\GoogleBusinessProfile\Performance\DataTransferObjects\MultiDailyMetricTimeSeries;
use ArtisanPackUI\GoogleBusinessProfile\Performance\PerformanceClient;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Request;

class StubPerformanceTokenProvider implements TokenProvider
{
    public function __construct( public string $token = 'stub-performance-token' )
    {
    }

    public function accessToken(): string
    {
        return $this->token;
    }
}

function gbpPerformanceClient(
    HttpFactory $http,
    ?TokenProvider $provider = null,
    int $maxAttempts = 1,
): PerformanceClient {
    return new PerformanceClient(
        tokenProvider: $provider ?? new StubPerformanceTokenProvider(),
        http: $http,
        timeout: 5,
        maxAttempts: $maxAttempts,
        retrySleepMs: 0,
    );
}

test( 'getDailyMetricsTimeSeries hits the Performance host with a Bearer token and returns a typed DTO', function (): void {
    $factory = new HttpFactory();
    $factory->fake( [
        'businessprofileperformance.googleapis.com/v1/locations/12345:getDailyMetricsTimeSeries*' => $factory::response(
            [
                'timeSeries' => [
                    'datedValues' => [
                        [ 'date' => [ 'year' => 2025, 'month' => 1, 'day' => 1 ], 'value' => '3' ],
                        [ 'date' => [ 'year' => 2025, 'month' => 1, 'day' => 2 ], 'value' => '5' ],
                    ],
                ],
            ],
            200,
        ),
    ] );

    $client = gbpPerformanceClient( $factory, new StubPerformanceTokenProvider( 'the-token' ) );

    $series = $client->getDailyMetricsTimeSeries(
        location : 'locations/12345',
        metric   : DailyMetric::CallClicks,
        startDate: new DateTimeImmutable( '2025-01-01' ),
        endDate  : new DateTimeImmutable( '2025-01-31' ),
    );

    expect( $series )->toBeInstanceOf( DailyMetricTimeSeries::class );
    expect( $series->metric )->toBe( DailyMetric::CallClicks );
    expect( $series->datedValues )->toHaveCount( 2 );
    expect( $series->datedValues[0]->value )->toBe( 3 );
    expect( $series->total() )->toBe( 8 );

    $factory->assertSent( function ( Request $request ): bool {
        $url = $request->url();

        return 'GET' === $request->method()
            && str_starts_with( $url, 'https://businessprofileperformance.googleapis.com/v1/locations/12345:getDailyMetricsTimeSeries' )
            && 'Bearer the-token' === $request->header( 'Authorization' )[0]
            && str_contains( $request->header( 'Accept' )[0], 'application/json' )
            && str_contains( $url, 'dailyMetric=CALL_CLICKS' )
            && str_contains( $url, 'dailyRange.startDate.year=2025' )
            && str_contains( $url, 'dailyRange.startDate.month=1' )
            && str_contains( $url, 'dailyRange.startDate.day=1' )
            && str_contains( $url, 'dailyRange.endDate.year=2025' )
            && str_contains( $url, 'dailyRange.endDate.month=1' )
            && str_contains( $url, 'dailyRange.endDate.day=31' );
    } );
} );

test( 'getDailyMetricsTimeSeries returns an empty series when the API omits datedValues', function (): void {
    $factory = new HttpFactory();
    $factory->fake( [ '*' => $factory::response( [], 200 ) ] );

    $client = gbpPerformanceClient( $factory );

    $series = $client->getDailyMetricsTimeSeries(
        location : 'locations/1',
        metric   : DailyMetric::WebsiteClicks,
        startDate: new DateTimeImmutable( '2025-01-01' ),
        endDate  : new DateTimeImmutable( '2025-01-31' ),
    );

    expect( $series->metric )->toBe( DailyMetric::WebsiteClicks );
    expect( $series->datedValues )->toBe( [] );
    expect( $series->total() )->toBe( 0 );
} );

test( 'getDailyMetricsTimeSeries rejects an empty location without making a request', function (): void {
    $factory = new HttpFactory();
    $factory->fake( [ '*' => $factory::response( [], 200 ) ] );

    $client = gbpPerformanceClient( $factory );

    expect( fn (): DailyMetricTimeSeries => $client->getDailyMetricsTimeSeries(
        location : '',
        metric   : DailyMetric::CallClicks,
        startDate: new DateTimeImmutable( '2025-01-01' ),
        endDate  : new DateTimeImmutable( '2025-01-31' ),
    ) )->toThrow( InvalidArgumentException::class );

    $factory->assertSentCount( 0 );
} );

test( 'getDailyMetricsTimeSeries rejects a reversed date range without making a request', function (): void {
    $factory = new HttpFactory();
    $factory->fake( [ '*' => $factory::response( [], 200 ) ] );

    $client = gbpPerformanceClient( $factory );

    expect( fn (): DailyMetricTimeSeries => $client->getDailyMetricsTimeSeries(
        location : 'locations/1',
        metric   : DailyMetric::CallClicks,
        startDate: new DateTimeImmutable( '2025-02-01' ),
        endDate  : new DateTimeImmutable( '2025-01-01' ),
    ) )->toThrow( InvalidArgumentException::class );

    $factory->assertSentCount( 0 );
} );

test( 'getDailyMetricsTimeSeries maps API errors to ApiException carrying the status and body', function (): void {
    $factory = new HttpFactory();
    $factory->fake( [ '*' => $factory::response( 'forbidden', 403 ) ] );

    $client = gbpPerformanceClient( $factory );

    $thrown = null;

    try {
        $client->getDailyMetricsTimeSeries(
            location : 'locations/1',
            metric   : DailyMetric::CallClicks,
            startDate: new DateTimeImmutable( '2025-01-01' ),
            endDate  : new DateTimeImmutable( '2025-01-31' ),
        );
    } catch ( ApiException $exception ) {
        $thrown = $exception;
    }

    expect( $thrown )->not->toBeNull();
    expect( $thrown->statusCode() )->toBe( 403 );
    expect( $thrown->responseBody() )->toBe( 'forbidden' );
} );

test( 'fetchMultiDailyMetricsTimeSeries repeats dailyMetrics without array-bracket suffixes', function (): void {
    $factory = new HttpFactory();
    $factory->fake( [
        'businessprofileperformance.googleapis.com/v1/locations/12345:fetchMultiDailyMetricsTimeSeries*' => $factory::response(
            [
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
            ],
            200,
        ),
    ] );

    $client = gbpPerformanceClient( $factory );

    $result = $client->fetchMultiDailyMetricsTimeSeries(
        location : 'locations/12345',
        metrics  : [ DailyMetric::CallClicks, DailyMetric::WebsiteClicks ],
        startDate: new DateTimeImmutable( '2025-01-01' ),
        endDate  : new DateTimeImmutable( '2025-01-31' ),
    );

    expect( $result )->toBeInstanceOf( MultiDailyMetricTimeSeries::class );
    expect( $result->series )->toHaveCount( 2 );
    expect( $result->for( DailyMetric::CallClicks )->datedValues[0]->value )->toBe( 2 );
    expect( $result->for( DailyMetric::WebsiteClicks )->datedValues[0]->value )->toBe( 9 );

    $factory->assertSent( function ( Request $request ): bool {
        $url = $request->url();

        return 'GET' === $request->method()
            && str_starts_with( $url, 'https://businessprofileperformance.googleapis.com/v1/locations/12345:fetchMultiDailyMetricsTimeSeries' )
            && str_contains( $url, 'dailyMetrics=CALL_CLICKS' )
            && str_contains( $url, 'dailyMetrics=WEBSITE_CLICKS' )
            && ! str_contains( $url, 'dailyMetrics%5B' )
            && ! str_contains( $url, 'dailyMetrics[' )
            && str_contains( $url, 'dailyRange.startDate.year=2025' )
            && str_contains( $url, 'dailyRange.endDate.day=31' );
    } );
} );

test( 'fetchMultiDailyMetricsTimeSeries rejects an empty location, empty metrics list, or reversed dates', function (): void {
    $factory = new HttpFactory();
    $factory->fake( [ '*' => $factory::response( [], 200 ) ] );

    $client = gbpPerformanceClient( $factory );

    expect( fn (): MultiDailyMetricTimeSeries => $client->fetchMultiDailyMetricsTimeSeries(
        location : '',
        metrics  : [ DailyMetric::CallClicks ],
        startDate: new DateTimeImmutable( '2025-01-01' ),
        endDate  : new DateTimeImmutable( '2025-01-31' ),
    ) )->toThrow( InvalidArgumentException::class );

    expect( fn (): MultiDailyMetricTimeSeries => $client->fetchMultiDailyMetricsTimeSeries(
        location : 'locations/1',
        metrics  : [],
        startDate: new DateTimeImmutable( '2025-01-01' ),
        endDate  : new DateTimeImmutable( '2025-01-31' ),
    ) )->toThrow( InvalidArgumentException::class );

    expect( fn (): MultiDailyMetricTimeSeries => $client->fetchMultiDailyMetricsTimeSeries(
        location : 'locations/1',
        metrics  : [ DailyMetric::CallClicks ],
        startDate: new DateTimeImmutable( '2025-02-01' ),
        endDate  : new DateTimeImmutable( '2025-01-01' ),
    ) )->toThrow( InvalidArgumentException::class );

    $factory->assertSentCount( 0 );
} );

test( 'fetchMultiDailyMetricsTimeSeries rejects a metrics list containing non-DailyMetric entries', function (): void {
    $factory = new HttpFactory();
    $factory->fake( [ '*' => $factory::response( [], 200 ) ] );

    $client = gbpPerformanceClient( $factory );

    expect( fn (): MultiDailyMetricTimeSeries => $client->fetchMultiDailyMetricsTimeSeries(
        location : 'locations/1',
        metrics  : [ DailyMetric::CallClicks, 'CALL_CLICKS' ],
        startDate: new DateTimeImmutable( '2025-01-01' ),
        endDate  : new DateTimeImmutable( '2025-01-31' ),
    ) )->toThrow( InvalidArgumentException::class );

    $factory->assertSentCount( 0 );
} );

test( 'fetchMultiDailyMetricsTimeSeries maps API errors to ApiException carrying the status and body', function (): void {
    $factory = new HttpFactory();
    $factory->fake( [ '*' => $factory::response( 'server error', 500 ) ] );

    $client = gbpPerformanceClient( $factory );

    $thrown = null;

    try {
        $client->fetchMultiDailyMetricsTimeSeries(
            location : 'locations/1',
            metrics  : [ DailyMetric::CallClicks ],
            startDate: new DateTimeImmutable( '2025-01-01' ),
            endDate  : new DateTimeImmutable( '2025-01-31' ),
        );
    } catch ( ApiException $exception ) {
        $thrown = $exception;
    }

    expect( $thrown )->not->toBeNull();
    expect( $thrown->statusCode() )->toBe( 500 );
    expect( $thrown->responseBody() )->toBe( 'server error' );
} );
