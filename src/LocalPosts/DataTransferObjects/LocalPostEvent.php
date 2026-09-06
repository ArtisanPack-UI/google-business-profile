<?php

/**
 * Event DTO for the Google Business Profile v4 Local Posts API.
 *
 * @package    ArtisanPack_UI
 * @subpackage GoogleBusinessProfile
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\GoogleBusinessProfile\LocalPosts\DataTransferObjects;

/**
 * Immutable representation of a local post's event schedule.
 *
 * Mirrors the `LocalPostEvent` sub-resource on a v4 `LocalPost`. Google
 * emits the schedule as a `TimeInterval` of `Date` and `TimeOfDay`
 * sub-objects (each with numeric `year`/`month`/`day` or
 * `hours`/`minutes`/`seconds`/`nanos` fields respectively). Those nested
 * shapes are preserved verbatim as associative arrays rather than expanded
 * into their own DTOs; callers that need the numeric parts can read them
 * directly off the array without the DTO layer imposing a lossy mapping.
 *
 * The event sub-resource is only emitted when the post's `topicType` is
 * `EVENT` or `OFFER`; on other topic types this DTO is absent from
 * {@see LocalPost::$event}.
 *
 * @package    ArtisanPack_UI
 * @subpackage GoogleBusinessProfile
 *
 * @since      1.0.0
 */
final class LocalPostEvent
{
    /**
     * Construct a LocalPostEvent DTO.
     *
     * @since 1.0.0
     *
     * @param  string|null  $title  Event title as displayed to users, or
     *                              null when the API omitted the field.
     * @param  array<string, mixed>|null  $startDate  `Date` sub-object
     *                                                (`year`, `month`, `day`),
     *                                                or null when absent.
     * @param  array<string, mixed>|null  $endDate    `Date` sub-object, or
     *                                                null when absent.
     * @param  array<string, mixed>|null  $startTime  `TimeOfDay` sub-object
     *                                                (`hours`, `minutes`,
     *                                                `seconds`, `nanos`), or
     *                                                null when absent.
     * @param  array<string, mixed>|null  $endTime    `TimeOfDay` sub-object,
     *                                                or null when absent.
     */
    public function __construct(
        public readonly ?string $title = null,
        public readonly ?array $startDate = null,
        public readonly ?array $endDate = null,
        public readonly ?array $startTime = null,
        public readonly ?array $endTime = null,
    ) {
    }

    /**
     * Build a LocalPostEvent from an API payload.
     *
     * The v4 wire shape nests the four schedule pieces under `schedule`, so
     * this factory unwraps them into the DTO's flat fields. Missing fields
     * remain null. Non-array schedule values (defensive) are ignored so a
     * malformed payload does not throw.
     *
     * @since 1.0.0
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray( array $data ): self
    {
        $title = null;

        if ( isset( $data['title'] ) && '' !== $data['title'] ) {
            $title = (string) $data['title'];
        }

        $schedule = ( isset( $data['schedule'] ) && is_array( $data['schedule'] ) ) ? $data['schedule'] : [];

        $startDate = ( isset( $schedule['startDate'] ) && is_array( $schedule['startDate'] ) ) ? $schedule['startDate'] : null;
        $endDate   = ( isset( $schedule['endDate'] ) && is_array( $schedule['endDate'] ) ) ? $schedule['endDate'] : null;
        $startTime = ( isset( $schedule['startTime'] ) && is_array( $schedule['startTime'] ) ) ? $schedule['startTime'] : null;
        $endTime   = ( isset( $schedule['endTime'] ) && is_array( $schedule['endTime'] ) ) ? $schedule['endTime'] : null;

        return new self( $title, $startDate, $endDate, $startTime, $endTime );
    }
}
