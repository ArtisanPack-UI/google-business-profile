<?php

/**
 * MultiDailyMetricTimeSeries DTO for the Performance API multi endpoint.
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
 * Result of {@see \ArtisanPackUI\GoogleBusinessProfile\Performance\PerformanceClient::fetchMultiDailyMetricsTimeSeries()}.
 *
 * The multi endpoint returns one {@see DailyMetricTimeSeries} per metric
 * the caller requested. The API's wire shape double-nests the entries
 * under `multiDailyMetricTimeSeries[].dailyMetricTimeSeries[]`; this DTO
 * flattens that into a single indexed list plus a metric->series lookup
 * so callers do not have to walk the nesting themselves.
 *
 * @package    ArtisanPack_UI
 * @subpackage GoogleBusinessProfile
 *
 * @since      1.0.0
 */
final class MultiDailyMetricTimeSeries
{
    /**
     * Construct a MultiDailyMetricTimeSeries.
     *
     * @since 1.0.0
     *
     * @param  list<DailyMetricTimeSeries>  $series  One entry per metric
     *                                                the caller requested
     *                                                that the API returned.
     */
    public function __construct(
        public readonly array $series,
    ) {
    }

    /**
     * Build the DTO from a `:fetchMultiDailyMetricsTimeSeries` response body.
     *
     * The API nests entries as
     * `multiDailyMetricTimeSeries[].dailyMetricTimeSeries[]`, so this
     * method walks both levels and hydrates every inner entry through
     * {@see DailyMetricTimeSeries::fromMultiEntry()}. Non-array entries
     * and entries naming an unknown metric are dropped rather than
     * throwing so the DTO tolerates future metrics Google may add.
     *
     * @since 1.0.0
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray( array $data ): self
    {
        $outer = $data['multiDailyMetricTimeSeries'] ?? [];

        $series = [];

        if ( is_array( $outer ) ) {
            foreach ( $outer as $group ) {
                if ( ! is_array( $group ) ) {
                    continue;
                }

                $inner = $group['dailyMetricTimeSeries'] ?? [];

                if ( ! is_array( $inner ) ) {
                    continue;
                }

                foreach ( $inner as $entry ) {
                    if ( ! is_array( $entry ) ) {
                        continue;
                    }

                    $hydrated = DailyMetricTimeSeries::fromMultiEntry( $entry );

                    if ( null !== $hydrated ) {
                        $series[] = $hydrated;
                    }
                }
            }
        }

        return new self( $series );
    }

    /**
     * Return the series for a given metric, or null when the response
     * did not include one for that metric.
     *
     * When the API returns duplicate entries for the same metric — a case
     * Google does not document but that would cost the caller a subtle
     * bug if silently overwritten — the first one wins. Callers that need
     * to see duplicates can iterate {@see $series} directly.
     *
     * @since 1.0.0
     */
    public function for( DailyMetric $metric ): ?DailyMetricTimeSeries
    {
        foreach ( $this->series as $entry ) {
            if ( $entry->metric === $metric ) {
                return $entry;
            }
        }

        return null;
    }
}
