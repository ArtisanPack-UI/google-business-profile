<?php

/**
 * Google Business Profile Business Information API client.
 *
 * @package    ArtisanPack_UI
 * @subpackage GoogleBusinessProfile
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\GoogleBusinessProfile\BusinessInformation;

use ArtisanPackUI\GoogleBusinessProfile\BusinessInformation\DataTransferObjects\Location;
use ArtisanPackUI\GoogleBusinessProfile\BusinessInformation\DataTransferObjects\LocationList;
use ArtisanPackUI\GoogleBusinessProfile\Http\BaseClient;
use InvalidArgumentException;

/**
 * Typed client for the Google Business Profile Business Information API.
 *
 * Exposes the endpoints needed to enumerate the locations belonging to an
 * account (`accounts.locations.list`), fetch a single location
 * (`locations.get`), and patch the writable fields of a location
 * (`locations.patch`) — the surface that powers hours, phone, address,
 * category, and website updates. Every request rides the shared
 * {@see BaseClient} plumbing: a Bearer token fetched from the injected
 * `TokenProvider`, JSON accept headers, retry on `429`/`5xx`, and mapping
 * of errors to {@see \ArtisanPackUI\GoogleBusinessProfile\Exceptions\ApiException}.
 *
 * The Business Information API lives on its own host separate from the
 * Account Management, Performance, and legacy v4 surfaces, so this client
 * is scoped to just that host and returns typed DTOs rather than raw
 * arrays.
 *
 * The API requires a `readMask` on both list and get responses and an
 * `updateMask` on patch requests. This client accepts a comma-separated
 * string or a list of field paths for both parameters and normalises them
 * to the wire format before sending.
 *
 * @package    ArtisanPack_UI
 * @subpackage GoogleBusinessProfile
 *
 * @since      1.0.0
 */
class BusinessInformationClient extends BaseClient
{
    /**
     * Google's documented maximum for the `pageSize` parameter on
     * `accounts.locations.list`. Requests larger than this are rejected by
     * the API, so the caller validates against this ceiling and throws
     * {@see InvalidArgumentException} to fail loudly during development
     * rather than after a network round-trip.
     *
     * @since 1.0.0
     *
     * @var int
     */
    public const MAX_PAGE_SIZE = 100;

    /**
     * List the locations attached to a parent account.
     *
     * Returns a single page. When {@see LocationList::$nextPageToken} is
     * non-null the caller should re-invoke this method with that token to
     * fetch the next page. `readMask` is required by the API and controls
     * which location fields are populated in the response.
     *
     * @since 1.0.0
     *
     * @param  string  $parent  Parent account resource name
     *                          (`accounts/{accountId}`).
     * @param  list<string>|string  $readMask  FieldMask selecting which
     *                                         location fields to return.
     *                                         Accepts a comma-separated
     *                                         string (`name,title`) or a
     *                                         list of paths.
     * @param  int|null  $pageSize  Requested page size (1-{@see self::MAX_PAGE_SIZE}).
     *                              Null omits the parameter and lets the
     *                              API pick its default.
     * @param  string|null  $pageToken  Token returned by a prior call's
     *                                  `nextPageToken`, or null for the
     *                                  first page.
     * @param  string|null  $filter  Optional API-side filter expression
     *                               (see Google's docs for supported
     *                               fields), or null to omit.
     * @param  string|null  $orderBy  Optional sort expression (e.g.
     *                                `title`, `storeCode desc`), or null
     *                                to accept the API's default ordering.
     *
     * @throws InvalidArgumentException When `parent` is empty, `readMask`
     *                                  normalises to empty, or `pageSize`
     *                                  is outside the documented
     *                                  1-{@see self::MAX_PAGE_SIZE} range.
     * @throws \ArtisanPackUI\GoogleBusinessProfile\Exceptions\ApiException
     *         When the API returns an error or the request fails at the
     *         transport level after all retries.
     */
    public function listLocations(
        string $parent,
        string|array $readMask,
        ?int $pageSize = null,
        ?string $pageToken = null,
        ?string $filter = null,
        ?string $orderBy = null,
    ): LocationList {
        if ( '' === $parent ) {
            throw new InvalidArgumentException( 'parent is required and must be a non-empty account resource name.' );
        }

        $query = [
            'readMask' => $this->normaliseFieldMask( $readMask, 'readMask' ),
        ];

        if ( null !== $pageSize ) {
            if ( $pageSize < 1 || $pageSize > self::MAX_PAGE_SIZE ) {
                throw new InvalidArgumentException( sprintf(
                    'pageSize must be between 1 and %d; %d given.',
                    self::MAX_PAGE_SIZE,
                    $pageSize,
                ) );
            }

            $query['pageSize'] = $pageSize;
        }

        if ( null !== $pageToken && '' !== $pageToken ) {
            $query['pageToken'] = $pageToken;
        }

        if ( null !== $filter && '' !== $filter ) {
            $query['filter'] = $filter;
        }

        if ( null !== $orderBy && '' !== $orderBy ) {
            $query['orderBy'] = $orderBy;
        }

        $body = $this->request( 'GET', $parent . '/locations', $query );

        return LocationList::fromArray( $body );
    }

    /**
     * Fetch a single location by resource name.
     *
     * `readMask` is optional but strongly recommended: without it the API
     * returns a minimal projection and later adds new fields silently. Pass
     * an explicit mask to lock the response shape.
     *
     * @since 1.0.0
     *
     * @param  string  $name  Location resource name
     *                        (`locations/{locationId}`).
     * @param  list<string>|string|null  $readMask  Optional FieldMask; see
     *                                              {@see listLocations()}
     *                                              for the accepted forms.
     *
     * @throws InvalidArgumentException When `name` is empty or `readMask`
     *                                  was supplied but normalises to
     *                                  empty.
     * @throws \ArtisanPackUI\GoogleBusinessProfile\Exceptions\ApiException
     *         When the API returns an error or the request fails at the
     *         transport level after all retries.
     */
    public function getLocation( string $name, string|array|null $readMask = null ): Location
    {
        if ( '' === $name ) {
            throw new InvalidArgumentException( 'name is required and must be a non-empty location resource name.' );
        }

        $query = [];

        if ( null !== $readMask ) {
            $query['readMask'] = $this->normaliseFieldMask( $readMask, 'readMask' );
        }

        $body = $this->request( 'GET', $name, $query );

        return Location::fromArray( $body );
    }

    /**
     * Patch the writable fields of a single location.
     *
     * `updateMask` is required by the API and enumerates exactly which
     * fields of the request body should be applied; fields not listed on
     * the mask are ignored even if present. Because the API's field paths
     * on write must match the location resource (e.g. `phoneNumbers`,
     * `regularHours`, `storefrontAddress`), the mask is normalised into
     * the wire format before sending.
     *
     * `validateOnly` performs a dry run when true: the API validates the
     * request without persisting any change, which is useful for
     * pre-flight checks in wizards.
     *
     * @since 1.0.0
     *
     * @param  string  $name  Location resource name
     *                        (`locations/{locationId}`).
     * @param  array<string, mixed>  $location  Partial Location payload
     *                                          containing only the fields
     *                                          being updated. The `name`
     *                                          field is injected
     *                                          automatically so callers
     *                                          need not repeat it.
     * @param  list<string>|string  $updateMask  FieldMask enumerating the
     *                                           fields to update. Accepts
     *                                           a comma-separated string
     *                                           (`phoneNumbers,regularHours`)
     *                                           or a list of paths.
     * @param  bool  $validateOnly  When true, ask the API to validate the
     *                              request without persisting; the
     *                              response still returns the (would-be)
     *                              Location.
     *
     * @throws InvalidArgumentException When `name` is empty, `location`
     *                                  is empty, or `updateMask`
     *                                  normalises to empty.
     * @throws \ArtisanPackUI\GoogleBusinessProfile\Exceptions\ApiException
     *         When the API returns an error or the request fails at the
     *         transport level after all retries.
     */
    public function patchLocation(
        string $name,
        array $location,
        string|array $updateMask,
        bool $validateOnly = false,
    ): Location {
        if ( '' === $name ) {
            throw new InvalidArgumentException( 'name is required and must be a non-empty location resource name.' );
        }

        if ( [] === $location ) {
            throw new InvalidArgumentException( 'location payload is required and must contain at least one field to update.' );
        }

        $query = [
            'updateMask' => $this->normaliseFieldMask( $updateMask, 'updateMask' ),
        ];

        if ( true === $validateOnly ) {
            $query['validateOnly'] = 'true';
        }

        $payload         = $location;
        $payload['name'] = $name;

        $body = $this->request( 'PATCH', $name, $query, $payload );

        return Location::fromArray( $body );
    }

    /**
     * Base URL for the Business Information API v1 surface.
     *
     * @since 1.0.0
     */
    protected function baseUrl(): string
    {
        return 'https://mybusinessbusinessinformation.googleapis.com/v1';
    }

    /**
     * Normalise a FieldMask argument to the comma-separated wire form.
     *
     * The Business Information API accepts a single comma-separated string
     * for `readMask` and `updateMask`. Callers may pass a pre-joined string
     * or a list of individual paths; both are supported and both are
     * validated against the empty case, which the API rejects with an
     * opaque error.
     *
     * @since 1.0.0
     *
     * @param  list<string>|string  $mask  The mask value to normalise.
     * @param  string  $parameter  The parameter name, used in the thrown
     *                             exception message so callers can tell
     *                             `readMask` and `updateMask` apart.
     *
     * @throws InvalidArgumentException When the resulting mask is empty.
     */
    private function normaliseFieldMask( string|array $mask, string $parameter ): string
    {
        $candidates = is_array( $mask ) ? $mask : explode( ',', $mask );

        $paths = [];

        foreach ( $candidates as $path ) {
            if ( ! is_string( $path ) ) {
                continue;
            }

            $trimmed = trim( $path );

            if ( '' !== $trimmed ) {
                $paths[] = $trimmed;
            }
        }

        if ( [] === $paths ) {
            throw new InvalidArgumentException( sprintf(
                '%s is required and must contain at least one field path.',
                $parameter,
            ) );
        }

        return implode( ',', $paths );
    }
}
