<?php

/**
 * DailyMetricTimeSeries DTO for the Google Business Profile Performance API.
 *
 * @package    ArtisanPack_UI
 * @subpackage GoogleBusinessProfile
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\GoogleBusinessProfile\Performance\DataTransferObjects;

/**
 * One metric's daily time series for a single location.
 *
 * Wraps the list of {@see DatedValue} entries the Performance API returns
 * for a single {@see DailyMetric} over a `DailyRange` window. Two
 * hydration paths share the DTO:
 *
 * - {@see fromSingleResponse()} — for the `:getDailyMetricsTimeSeries`
 *   endpoint, where the response wraps the series in `timeSeries` and
 *   does not echo the requested metric back; the caller passes the metric
 *   in so the DTO can carry it.
 * - {@see fromMultiEntry()} — for one entry inside
 *   `:fetchMultiDailyMetricsTimeSeries`, where each entry names its own
 *   metric on `dailyMetric`.
 *
 * `datedValues` is always a list (never null) so callers can iterate
 * without a null guard; a metric with no reported days is an empty list.
 *
 * @package    ArtisanPack_UI
 * @subpackage GoogleBusinessProfile
 *
 * @since      1.0.0
 */
final class DailyMetricTimeSeries
{
    /**
     * Construct a DailyMetricTimeSeries.
     *
     * @since 1.0.0
     *
     * @param  DailyMetric  $metric  The metric this series reports on.
     * @param  list<DatedValue>  $datedValues  One entry per calendar day
     *                                         the API returned.
     */
    public function __construct(
        public readonly DailyMetric $metric,
        public readonly array $datedValues,
    ) {
    }

    /**
     * Build a series from a `:getDailyMetricsTimeSeries` response body.
     *
     * The single-metric endpoint does not echo the requested metric on the
     * response, so the caller passes it in. A response missing the
     * expected `timeSeries.datedValues` array becomes an empty series
     * rather than throwing, matching how the API omits the key when the
     * date range contains no reported days.
     *
     * @since 1.0.0
     *
     * @param  DailyMetric  $metric  The metric the caller requested.
     * @param  array<string, mixed>  $data  The decoded response body.
     */
    public static function fromSingleResponse( DailyMetric $metric, array $data ): self
    {
        $timeSeries = ( isset( $data['timeSeries'] ) && is_array( $data['timeSeries'] ) ) ? $data['timeSeries'] : [];

        return new self( $metric, self::hydrateDatedValues( $timeSeries ) );
    }

    /**
     * Build a series from one entry of a
     * `:fetchMultiDailyMetricsTimeSeries` response.
     *
     * The multi endpoint echoes the metric name on each series entry.
     * When the entry names an unknown metric string (one the client's
     * enum does not yet know about — e.g. a value Google added after this
     * release) the entry is dropped by returning null; callers filter
     * these out so a rogue future metric does not throw at hydration.
     *
     * @since 1.0.0
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromMultiEntry( array $data ): ?self
    {
        if ( ! isset( $data['dailyMetric'] ) || ! is_string( $data['dailyMetric'] ) ) {
            return null;
        }

        $metric = DailyMetric::tryFrom( $data['dailyMetric'] );

        if ( null === $metric ) {
            return null;
        }

        $timeSeries = ( isset( $data['timeSeries'] ) && is_array( $data['timeSeries'] ) ) ? $data['timeSeries'] : [];

        return new self( $metric, self::hydrateDatedValues( $timeSeries ) );
    }

    /**
     * Sum every day's value in the series.
     *
     * Convenience for the common "total impressions for the window" case;
     * an empty series returns 0.
     *
     * @since 1.0.0
     */
    public function total(): int
    {
        $total = 0;

        foreach ( $this->datedValues as $value ) {
            $total += $value->value;
        }

        return $total;
    }

    /**
     * Hydrate the `datedValues` array inside a `timeSeries` payload.
     *
     * Non-array entries are skipped so a malformed row cannot poison the
     * rest of the list; missing keys collapse to an empty list.
     *
     * @since 1.0.0
     *
     * @param  array<string, mixed>  $timeSeries
     *
     * @return list<DatedValue>
     */
    private static function hydrateDatedValues( array $timeSeries ): array
    {
        $raw = $timeSeries['datedValues'] ?? [];

        if ( ! is_array( $raw ) ) {
            return [];
        }

        $values = [];

        foreach ( $raw as $entry ) {
            if ( is_array( $entry ) ) {
                $values[] = DatedValue::fromArray( $entry );
            }
        }

        return $values;
    }
}
