<?php

/**
 * Paginated location list DTO for the Business Information API.
 *
 * @package    ArtisanPack_UI
 * @subpackage GoogleBusinessProfile
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\GoogleBusinessProfile\BusinessInformation\DataTransferObjects;

/**
 * Immutable, single-page result returned by
 * {@see \ArtisanPackUI\GoogleBusinessProfile\BusinessInformation\BusinessInformationClient::listLocations()}.
 *
 * Wraps the array of {@see Location} DTOs together with the pagination
 * token the API returns when more pages are available and the total
 * account-wide count. `nextPageToken` is null on the final page, matching
 * the API's own contract of only emitting the field when there is more to
 * fetch. `totalSize` is null when the API omits it, which happens on any
 * request that did not opt into the count.
 *
 * @package    ArtisanPack_UI
 * @subpackage GoogleBusinessProfile
 *
 * @since      1.0.0
 */
final class LocationList
{
    /**
     * Construct a LocationList.
     *
     * @since 1.0.0
     *
     * @param  list<Location>  $locations  Locations on this page.
     * @param  string|null  $nextPageToken  Token for fetching the next
     *                                      page, or null when this is the
     *                                      final page.
     * @param  int|null  $totalSize  Total account-wide location count, or
     *                               null when the API did not include it.
     */
    public function __construct(
        public readonly array $locations,
        public readonly ?string $nextPageToken = null,
        public readonly ?int $totalSize = null,
    ) {
    }

    /**
     * Build a LocationList from a decoded `locations.list` response.
     *
     * A response with no `locations` key (or a non-array value there) is
     * treated as an empty page, matching how the API omits the key when
     * the account has no locations. Any entry that is not itself an
     * associative array is skipped so a malformed row does not poison
     * the rest of the page.
     *
     * @since 1.0.0
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray( array $data ): self
    {
        $rawLocations = $data['locations'] ?? [];

        $locations = [];

        if ( is_array( $rawLocations ) ) {
            foreach ( $rawLocations as $rawLocation ) {
                if ( is_array( $rawLocation ) ) {
                    $locations[] = Location::fromArray( $rawLocation );
                }
            }
        }

        $nextPageToken = null;

        if ( isset( $data['nextPageToken'] ) && '' !== $data['nextPageToken'] ) {
            $nextPageToken = (string) $data['nextPageToken'];
        }

        $totalSize = null;

        if ( isset( $data['totalSize'] ) && is_numeric( $data['totalSize'] ) ) {
            $totalSize = (int) $data['totalSize'];
        }

        return new self( $locations, $nextPageToken, $totalSize );
    }

    /**
     * Whether this page carries a token that would fetch a further page.
     *
     * @since 1.0.0
     */
    public function hasMore(): bool
    {
        return null !== $this->nextPageToken;
    }
}
