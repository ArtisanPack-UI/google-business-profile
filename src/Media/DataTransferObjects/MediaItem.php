<?php

/**
 * MediaItem DTO for the Google Business Profile v4 Media API.
 *
 * @package    ArtisanPack_UI
 * @subpackage GoogleBusinessProfile
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\GoogleBusinessProfile\Media\DataTransferObjects;

/**
 * Immutable representation of a standalone Google Business Profile media item.
 *
 * Mirrors the `MediaItem` resource returned by the v4 Media API's
 * `accounts.locations.media.create`, `media.get`, and `media.list` endpoints.
 * Distinct from {@see \ArtisanPackUI\GoogleBusinessProfile\LocalPosts\DataTransferObjects\MediaItem}:
 * the local-posts variant models the trimmed shape Google emits inside a
 * `LocalPost.media[]` array, while this DTO models the fuller standalone
 * resource (`name`, `locationAssociation`, `dimensions`, `insights`, and
 * `attribution` in addition to the shared fields).
 *
 * Enum-shaped fields (`mediaFormat`) are kept as plain strings so callers see
 * whatever value the API returned — including values Google may add later —
 * without the DTO layer silently dropping them. Object-shaped fields the
 * package does not model as their own DTO (`locationAssociation`,
 * `dimensions`, `insights`, `attribution`) are exposed as raw associative
 * arrays for the same reason.
 *
 * The full payload — every field the API returned — is preserved on
 * {@see self::$raw}.
 *
 * @package    ArtisanPack_UI
 * @subpackage GoogleBusinessProfile
 *
 * @since      1.0.0
 */
final class MediaItem
{
    /**
     * Construct a MediaItem DTO.
     *
     * @since 1.0.0
     *
     * @param  string  $name  Resource name
     *                        (`accounts/{a}/locations/{l}/media/{mediaKey}`),
     *                        empty when the API omitted it (only possible on
     *                        malformed responses).
     * @param  string|null  $mediaFormat  Media format enum value (e.g.
     *                                    `PHOTO`, `VIDEO`), or null when
     *                                    absent.
     * @param  array<string, mixed>|null  $locationAssociation  Association
     *                                                          object
     *                                                          identifying
     *                                                          how the item
     *                                                          is attached
     *                                                          to the
     *                                                          location
     *                                                          (typically
     *                                                          `category` or
     *                                                          `priceListItemId`),
     *                                                          or null.
     * @param  string|null  $googleUrl  Google-hosted display URL, or null.
     * @param  string|null  $thumbnailUrl  Google-hosted thumbnail URL, or null.
     * @param  string|null  $createTime  RFC 3339 timestamp for when the item
     *                                   was created, or null.
     * @param  array<string, mixed>|null  $dimensions  Pixel dimensions
     *                                                 (`widthPixels`,
     *                                                 `heightPixels`), or
     *                                                 null.
     * @param  array<string, mixed>|null  $insights  Aggregate view metrics
     *                                               (`viewCount`), or null.
     * @param  array<string, mixed>|null  $attribution  Customer attribution
     *                                                  payload, or null.
     * @param  string|null  $description  Caller-supplied description, or null.
     * @param  string|null  $sourceUrl  Caller-provided URL Google fetched
     *                                  the media from, or null when the
     *                                  media was uploaded by bytes rather
     *                                  than referenced by URL.
     * @param  MediaItemDataRef|null  $dataRef  Data-ref pointer used when
     *                                          the media was uploaded via
     *                                          the resumable byte-upload
     *                                          flow, or null.
     * @param  array<string, mixed>  $raw  The raw payload for this media
     *                                     item exactly as returned by the
     *                                     API.
     */
    public function __construct(
        public readonly string $name,
        public readonly ?string $mediaFormat = null,
        public readonly ?array $locationAssociation = null,
        public readonly ?string $googleUrl = null,
        public readonly ?string $thumbnailUrl = null,
        public readonly ?string $createTime = null,
        public readonly ?array $dimensions = null,
        public readonly ?array $insights = null,
        public readonly ?array $attribution = null,
        public readonly ?string $description = null,
        public readonly ?string $sourceUrl = null,
        public readonly ?MediaItemDataRef $dataRef = null,
        public readonly array $raw = [],
    ) {
    }

    /**
     * Build a MediaItem from an API payload.
     *
     * Missing fields are coerced to sensible defaults rather than throwing:
     * only `name` is guaranteed by the API for a stored media item, and even
     * that is coerced to an empty string on a malformed response. Object-
     * shaped sub-payloads are preserved as associative arrays only when the
     * wire value is itself an associative array; scalar or list values under
     * these keys are ignored. Unknown fields survive on {@see self::$raw}.
     *
     * @since 1.0.0
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray( array $data ): self
    {
        $dataRef = null;

        if ( isset( $data['dataRef'] ) && is_array( $data['dataRef'] ) ) {
            $dataRef = MediaItemDataRef::fromArray( $data['dataRef'] );
        }

        return new self(
            name               : isset( $data['name'] ) ? (string) $data['name'] : '',
            mediaFormat        : self::nullableString( $data, 'mediaFormat' ),
            locationAssociation: self::nullableAssoc( $data, 'locationAssociation' ),
            googleUrl          : self::nullableString( $data, 'googleUrl' ),
            thumbnailUrl       : self::nullableString( $data, 'thumbnailUrl' ),
            createTime         : self::nullableString( $data, 'createTime' ),
            dimensions         : self::nullableAssoc( $data, 'dimensions' ),
            insights           : self::nullableAssoc( $data, 'insights' ),
            attribution        : self::nullableAssoc( $data, 'attribution' ),
            description        : self::nullableString( $data, 'description' ),
            sourceUrl          : self::nullableString( $data, 'sourceUrl' ),
            dataRef            : $dataRef,
            raw                : $data,
        );
    }

    /**
     * Read a nullable string field from the raw payload.
     *
     * Returns null when the key is missing or the value is the empty string;
     * casts every other value to string.
     *
     * @since 1.0.0
     *
     * @param  array<string, mixed>  $data
     */
    private static function nullableString( array $data, string $key ): ?string
    {
        if ( ! isset( $data[ $key ] ) || '' === $data[ $key ] ) {
            return null;
        }

        return (string) $data[ $key ];
    }

    /**
     * Read a nullable associative-array field from the raw payload.
     *
     * Returns null when the key is missing or the value is not an
     * associative array (a list under this key is treated as absent because
     * the wire contract calls for an object).
     *
     * @since 1.0.0
     *
     * @param  array<string, mixed>  $data
     *
     * @return array<string, mixed>|null
     */
    private static function nullableAssoc( array $data, string $key ): ?array
    {
        if ( ! isset( $data[ $key ] ) || ! is_array( $data[ $key ] ) ) {
            return null;
        }

        if ( [] !== $data[ $key ] && array_is_list( $data[ $key ] ) ) {
            return null;
        }

        return $data[ $key ];
    }
}
