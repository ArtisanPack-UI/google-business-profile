<?php

/**
 * DatedValue DTO for the Google Business Profile Performance API.
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

use DateTimeImmutable;
use DateTimeZone;

/**
 * One day's value from a metric's time series.
 *
 * Mirrors the `DatedValue` shape returned inside a `TimeSeries`: a Google
 * `Date` sub-object (year / month / day, no time component) and a `value`
 * the API delivers as a JSON string so counts above 2^53 do not lose
 * precision on the wire. The DTO stores year/month/day as ints for
 * ergonomic access and coerces `value` to an int; days the location saw no
 * activity are surfaced as `0` rather than as a missing entry (matching
 * how Google reports them).
 *
 * @package    ArtisanPack_UI
 * @subpackage GoogleBusinessProfile
 *
 * @since      1.0.0
 */
final class DatedValue
{
    /**
     * Construct a DatedValue.
     *
     * @since 1.0.0
     *
     * @param  int  $year  Four-digit calendar year (e.g. 2025).
     * @param  int  $month  Month of the year, 1-12.
     * @param  int  $day  Day of the month, 1-31.
     * @param  int  $value  Metric value for the day; 0 for a day the API
     *                      reports as inactive.
     */
    public function __construct(
        public readonly int $year,
        public readonly int $month,
        public readonly int $day,
        public readonly int $value,
    ) {
    }

    /**
     * Build a DatedValue from a decoded `datedValues[]` entry.
     *
     * Missing pieces of the Google `Date` sub-object fall back to `0` on
     * the year/month/day fields (mirroring the API's own semantics for a
     * "partial" Date) so a malformed payload cannot throw during hydration.
     * The `value` field is JSON-encoded as a string by Google and is
     * coerced through {@see intval()} for numeric access; a missing or
     * non-numeric value normalises to `0`.
     *
     * @since 1.0.0
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray( array $data ): self
    {
        $date = ( isset( $data['date'] ) && is_array( $data['date'] ) ) ? $data['date'] : [];

        $year  = isset( $date['year'] ) && is_numeric( $date['year'] ) ? (int) $date['year'] : 0;
        $month = isset( $date['month'] ) && is_numeric( $date['month'] ) ? (int) $date['month'] : 0;
        $day   = isset( $date['day'] ) && is_numeric( $date['day'] ) ? (int) $date['day'] : 0;

        $value = isset( $data['value'] ) && is_numeric( $data['value'] ) ? (int) $data['value'] : 0;

        return new self( $year, $month, $day, $value );
    }

    /**
     * Return this value's date as a UTC {@see DateTimeImmutable} at midnight.
     *
     * The API reports metrics per calendar day with no time component;
     * anchoring to UTC 00:00 gives callers a stable comparable datetime
     * for sorting, formatting, and chart plotting without having to
     * marshal year/month/day themselves. Returns null when the underlying
     * date components are incomplete (any of year/month/day is 0), so
     * malformed payloads do not silently become `0000-00-00`.
     *
     * @since 1.0.0
     */
    public function toDate(): ?DateTimeImmutable
    {
        if ( 0 === $this->year || 0 === $this->month || 0 === $this->day ) {
            return null;
        }

        return DateTimeImmutable::createFromFormat(
            '!Y-n-j',
            sprintf( '%d-%d-%d', $this->year, $this->month, $this->day ),
            new DateTimeZone( 'UTC' ),
        ) ?: null;
    }
}
