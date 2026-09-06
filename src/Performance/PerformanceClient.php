<?php

/**
 * Google Business Profile Performance API client.
 *
 * @package    ArtisanPack_UI
 * @subpackage GoogleBusinessProfile
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\GoogleBusinessProfile\Performance;

use ArtisanPackUI\GoogleBusinessProfile\Http\BaseClient;
use ArtisanPackUI\GoogleBusinessProfile\Performance\DataTransferObjects\DailyMetric;
use ArtisanPackUI\GoogleBusinessProfile\Performance\DataTransferObjects\DailyMetricTimeSeries;
use ArtisanPackUI\GoogleBusinessProfile\Performance\DataTransferObjects\MultiDailyMetricTimeSeries;
use DateTimeInterface;
use InvalidArgumentException;

/**
 * Typed client for the Google Business Profile Performance API.
 *
 * Exposes the two endpoints needed for daily metric reporting:
 *
 * - `locations/{id}:getDailyMetricsTimeSeries` — one metric over a date
 *   range, returned as a {@see DailyMetricTimeSeries}.
 * - `locations/{id}:fetchMultiDailyMetricsTimeSeries` — many metrics in a
 *   single round-trip, returned as a {@see MultiDailyMetricTimeSeries}.
 *
 * The Performance API lives on its own host separate from the Account
 * Management, Business Information, and legacy v4 surfaces, so this
 * client is scoped to just that host and returns typed DTOs rather than
 * raw arrays. Every request rides the shared {@see BaseClient} plumbing:
 * a Bearer token fetched from the injected `TokenProvider`, JSON accept
 * headers, retry on `429`/`5xx`, and mapping of errors to
 * {@see \ArtisanPackUI\GoogleBusinessProfile\Exceptions\ApiException}.
 *
 * The API accepts date-range bounds as a `DailyRange` composed of two
 * Google `Date` sub-objects. Both methods here take {@see DateTimeInterface}
 * for ergonomics and split each value into year/month/day query params
 * matching the API's dot-notation shape (`dailyRange.startDate.year=...`).
 *
 * @package    ArtisanPack_UI
 * @subpackage GoogleBusinessProfile
 *
 * @since      1.0.0
 */
class PerformanceClient extends BaseClient
{
    /**
     * Fetch a single daily metric's time series for a location.
     *
     * @since 1.0.0
     *
     * @param  string  $location  Location resource name
     *                            (`locations/{locationId}`).
     * @param  DailyMetric  $metric  The metric to report on.
     * @param  DateTimeInterface  $startDate  Inclusive start of the range.
     * @param  DateTimeInterface  $endDate  Inclusive end of the range.
     *
     * @throws InvalidArgumentException When `location` is empty or
     *                                  `endDate` is before `startDate`.
     * @throws \ArtisanPackUI\GoogleBusinessProfile\Exceptions\ApiException
     *         When the API returns an error or the request fails at the
     *         transport level after all retries.
     */
    public function getDailyMetricsTimeSeries(
        string $location,
        DailyMetric $metric,
        DateTimeInterface $startDate,
        DateTimeInterface $endDate,
    ): DailyMetricTimeSeries {
        if ( '' === $location ) {
            throw new InvalidArgumentException( 'location is required and must be a non-empty location resource name.' );
        }

        $this->assertDateOrder( $startDate, $endDate );

        $query = array_merge(
            [ 'dailyMetric' => $metric->value ],
            $this->dailyRangeQuery( $startDate, $endDate ),
        );

        $body = $this->request( 'GET', $location . ':getDailyMetricsTimeSeries', $query );

        return DailyMetricTimeSeries::fromSingleResponse( $metric, $body );
    }

    /**
     * Fetch several daily metrics' time series for a location in one call.
     *
     * The API accepts `dailyMetrics` as a repeated query parameter (no
     * `[]` suffix). Guzzle's default query encoder writes repeated array
     * values with numeric bracket suffixes, which the API rejects, so
     * the query string is built manually and appended to the URL.
     *
     * @since 1.0.0
     *
     * @param  string  $location  Location resource name
     *                            (`locations/{locationId}`).
     * @param  list<DailyMetric>  $metrics  One or more metrics to report on.
     * @param  DateTimeInterface  $startDate  Inclusive start of the range.
     * @param  DateTimeInterface  $endDate  Inclusive end of the range.
     *
     * @throws InvalidArgumentException When `location` is empty, `metrics`
     *                                  is empty, or `endDate` is before
     *                                  `startDate`.
     * @throws \ArtisanPackUI\GoogleBusinessProfile\Exceptions\ApiException
     *         When the API returns an error or the request fails at the
     *         transport level after all retries.
     */
    public function fetchMultiDailyMetricsTimeSeries(
        string $location,
        array $metrics,
        DateTimeInterface $startDate,
        DateTimeInterface $endDate,
    ): MultiDailyMetricTimeSeries {
        if ( '' === $location ) {
            throw new InvalidArgumentException( 'location is required and must be a non-empty location resource name.' );
        }

        if ( [] === $metrics ) {
            throw new InvalidArgumentException( 'metrics is required and must contain at least one DailyMetric.' );
        }

        foreach ( $metrics as $metric ) {
            if ( ! $metric instanceof DailyMetric ) {
                throw new InvalidArgumentException( 'metrics must contain only DailyMetric enum values.' );
            }
        }

        $this->assertDateOrder( $startDate, $endDate );

        $pathWithQuery = $location . ':fetchMultiDailyMetricsTimeSeries?' . $this->buildMultiQueryString( $metrics, $startDate, $endDate );

        $body = $this->request( 'GET', $pathWithQuery );

        return MultiDailyMetricTimeSeries::fromArray( $body );
    }

    /**
     * Base URL for the Performance API v1 surface.
     *
     * @since 1.0.0
     */
    protected function baseUrl(): string
    {
        return 'https://businessprofileperformance.googleapis.com/v1';
    }

    /**
     * Enforce that the end of the requested range is not before its start.
     *
     * The API also rejects reversed ranges, but the check is cheap to run
     * client-side and gives a clearer error than the opaque server 400.
     *
     * @since 1.0.0
     *
     * @throws InvalidArgumentException When `endDate` is strictly before
     *                                  `startDate`.
     */
    private function assertDateOrder( DateTimeInterface $startDate, DateTimeInterface $endDate ): void
    {
        if ( $endDate < $startDate ) {
            throw new InvalidArgumentException( 'endDate must not be before startDate.' );
        }
    }

    /**
     * Build the six `dailyRange.*` query params for a start/end pair.
     *
     * Google models the range as a `DailyRange` of two `Date` sub-objects
     * and expects dot-notation query params rather than a JSON body on
     * these GET endpoints.
     *
     * @since 1.0.0
     *
     * @return array<string, int>
     */
    private function dailyRangeQuery( DateTimeInterface $startDate, DateTimeInterface $endDate ): array
    {
        return [
            'dailyRange.startDate.year'  => (int) $startDate->format( 'Y' ),
            'dailyRange.startDate.month' => (int) $startDate->format( 'n' ),
            'dailyRange.startDate.day'   => (int) $startDate->format( 'j' ),
            'dailyRange.endDate.year'    => (int) $endDate->format( 'Y' ),
            'dailyRange.endDate.month'   => (int) $endDate->format( 'n' ),
            'dailyRange.endDate.day'     => (int) $endDate->format( 'j' ),
        ];
    }

    /**
     * Assemble the full query string for the multi endpoint by hand so
     * the repeated `dailyMetrics` values are emitted without `[]` array
     * suffixes.
     *
     * @since 1.0.0
     *
     * @param  list<DailyMetric>  $metrics
     */
    private function buildMultiQueryString(
        array $metrics,
        DateTimeInterface $startDate,
        DateTimeInterface $endDate,
    ): string {
        $parts = [];

        foreach ( $metrics as $metric ) {
            $parts[] = 'dailyMetrics=' . rawurlencode( $metric->value );
        }

        foreach ( $this->dailyRangeQuery( $startDate, $endDate ) as $key => $value ) {
            $parts[] = $key . '=' . $value;
        }

        return implode( '&', $parts );
    }
}
