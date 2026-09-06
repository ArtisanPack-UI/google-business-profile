<?php

/**
 * Location DTO for the Google Business Profile Business Information API.
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
 * Immutable representation of a single Google Business Profile location.
 *
 * Mirrors the `Location` resource returned by the Business Information API's
 * `locations.get` and `accounts.locations.list` endpoints. Scalar fields
 * (title, storeCode, websiteUri, ...) are typed for ergonomics; the deeply
 * nested sub-resources the API attaches to a location — addresses, phone
 * numbers, categories, hours, service areas, open state, metadata, and so
 * on — are kept as plain associative arrays so the DTO layer does not
 * silently drop or reshape data.
 *
 * The full location payload — every field the API returned, whether or not
 * it also appears as a typed property — is preserved on {@see self::$raw}
 * so consumers can reach fields the typed surface does not yet expose (for
 * example, `serviceItems`, `moreHours`, or `relationshipData`) without a
 * round-trip. Because typed fields are duplicated on `$raw`, treat it as
 * the source of truth for the wire payload rather than as a "leftovers" bag.
 *
 * @package    ArtisanPack_UI
 * @subpackage GoogleBusinessProfile
 *
 * @since      1.0.0
 */
final class Location
{
    /**
     * Construct a Location DTO.
     *
     * @since 1.0.0
     *
     * @param  string  $name  Resource name (`locations/{locationId}`).
     * @param  string  $title  Human-readable location title.
     * @param  string|null  $languageCode  BCP-47 language code the location
     *                                     was authored in, or null when
     *                                     absent.
     * @param  string|null  $storeCode  Merchant-supplied store code, or
     *                                  null when absent.
     * @param  string|null  $websiteUri  Public-facing website URL, or null
     *                                   when absent.
     * @param  array<string, mixed>|null  $phoneNumbers  Phone numbers block
     *                                                   (`primaryPhone`,
     *                                                   `additionalPhones`),
     *                                                   or null when absent.
     * @param  array<string, mixed>|null  $categories  Categories block
     *                                                 (`primaryCategory`,
     *                                                 `additionalCategories`),
     *                                                 or null when absent.
     * @param  array<string, mixed>|null  $storefrontAddress  PostalAddress
     *                                                        payload, or
     *                                                        null when the
     *                                                        location has
     *                                                        no storefront
     *                                                        (service-area
     *                                                        businesses).
     * @param  array<string, mixed>|null  $regularHours  Weekly opening
     *                                                   hours payload, or
     *                                                   null when absent.
     * @param  array<string, mixed>|null  $specialHours  Holiday/special
     *                                                   hours payload, or
     *                                                   null when absent.
     * @param  array<string, mixed>|null  $serviceArea  Service area block
     *                                                  for service-area
     *                                                  businesses, or null
     *                                                  when absent.
     * @param  list<string>|null  $labels  Merchant-supplied labels, or null
     *                                     when absent.
     * @param  array<string, mixed>|null  $latlng  Latitude/longitude
     *                                             payload, or null when
     *                                             absent.
     * @param  array<string, mixed>|null  $openInfo  Open-state payload
     *                                               (`status`, `canReopen`,
     *                                               `openingDate`), or null
     *                                               when absent.
     * @param  array<string, mixed>|null  $metadata  Location metadata
     *                                               payload, or null when
     *                                               absent.
     * @param  array<string, mixed>|null  $profile  Profile payload
     *                                              (`description`), or null
     *                                              when absent.
     * @param  array<string, mixed>  $raw  The raw payload for this location
     *                                     exactly as returned by the API.
     */
    public function __construct(
        public readonly string $name,
        public readonly string $title,
        public readonly ?string $languageCode = null,
        public readonly ?string $storeCode = null,
        public readonly ?string $websiteUri = null,
        public readonly ?array $phoneNumbers = null,
        public readonly ?array $categories = null,
        public readonly ?array $storefrontAddress = null,
        public readonly ?array $regularHours = null,
        public readonly ?array $specialHours = null,
        public readonly ?array $serviceArea = null,
        public readonly ?array $labels = null,
        public readonly ?array $latlng = null,
        public readonly ?array $openInfo = null,
        public readonly ?array $metadata = null,
        public readonly ?array $profile = null,
        public readonly array $raw = [],
    ) {
    }

    /**
     * Build a Location from an API payload.
     *
     * Missing fields are coerced to sensible defaults rather than throwing:
     * only `name` is guaranteed by the API for a returned location, and
     * even the human-readable `title` may be omitted while a listing is
     * still being edited. Unknown fields survive on {@see self::$raw}.
     *
     * @since 1.0.0
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray( array $data ): self
    {
        return new self(
            name             : isset( $data['name'] ) ? (string) $data['name'] : '',
            title            : isset( $data['title'] ) ? (string) $data['title'] : '',
            languageCode     : isset( $data['languageCode'] ) ? (string) $data['languageCode'] : null,
            storeCode        : isset( $data['storeCode'] ) ? (string) $data['storeCode'] : null,
            websiteUri       : isset( $data['websiteUri'] ) ? (string) $data['websiteUri'] : null,
            phoneNumbers     : self::arrayOrNull( $data, 'phoneNumbers' ),
            categories       : self::arrayOrNull( $data, 'categories' ),
            storefrontAddress: self::arrayOrNull( $data, 'storefrontAddress' ),
            regularHours     : self::arrayOrNull( $data, 'regularHours' ),
            specialHours     : self::arrayOrNull( $data, 'specialHours' ),
            serviceArea      : self::arrayOrNull( $data, 'serviceArea' ),
            labels           : self::labelsOrNull( $data ),
            latlng           : self::arrayOrNull( $data, 'latlng' ),
            openInfo         : self::arrayOrNull( $data, 'openInfo' ),
            metadata         : self::arrayOrNull( $data, 'metadata' ),
            profile          : self::arrayOrNull( $data, 'profile' ),
            raw              : $data,
        );
    }

    /**
     * Return the value at `$key` when it is an array, otherwise null.
     *
     * Guards against malformed payloads (scalar where an object is expected)
     * so a rogue value on one field does not blow up the whole DTO.
     *
     * @since 1.0.0
     *
     * @param  array<string, mixed>  $data
     *
     * @return array<string, mixed>|null
     */
    private static function arrayOrNull( array $data, string $key ): ?array
    {
        if ( ! isset( $data[ $key ] ) || ! is_array( $data[ $key ] ) ) {
            return null;
        }

        return $data[ $key ];
    }

    /**
     * Coerce the `labels` payload into a list of strings, or null when the
     * field is absent or not an array. Non-string entries are skipped.
     *
     * @since 1.0.0
     *
     * @param  array<string, mixed>  $data
     *
     * @return list<string>|null
     */
    private static function labelsOrNull( array $data ): ?array
    {
        if ( ! isset( $data['labels'] ) || ! is_array( $data['labels'] ) ) {
            return null;
        }

        $labels = [];

        foreach ( $data['labels'] as $label ) {
            if ( is_string( $label ) ) {
                $labels[] = $label;
            }
        }

        return $labels;
    }
}
