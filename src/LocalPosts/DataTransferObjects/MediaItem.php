<?php

/**
 * MediaItem DTO for the Google Business Profile v4 Local Posts API.
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
 * Immutable representation of a single media item attached to a local post.
 *
 * Mirrors the entries of the v4 `LocalPost.media[]` array. The full media
 * payload — every field the API returned, whether or not it also appears as
 * a typed property — is preserved on {@see self::$raw} so consumers can
 * reach fields the typed surface does not yet expose (dimensions, insights,
 * attribution, etc.) without a round-trip.
 *
 * The `mediaFormat` field is a string enum documented by Google with values
 * `MEDIA_FORMAT_UNSPECIFIED`, `PHOTO`, and `VIDEO`, but is preserved
 * verbatim so callers see any value Google may add later without the DTO
 * layer silently dropping it.
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
     * @param  string|null  $mediaFormat  Media format enum value (e.g.
     *                                    `PHOTO`, `VIDEO`), or null when
     *                                    the API omitted the field.
     * @param  string|null  $sourceUrl  Caller-provided URL Google fetched
     *                                  the media from, or null when the
     *                                  media was uploaded rather than
     *                                  referenced by URL.
     * @param  string|null  $googleUrl  Google-hosted display URL, or null
     *                                  when the API omitted the field.
     * @param  array<string, mixed>  $raw  The raw payload for this media
     *                                     item exactly as returned by the
     *                                     API.
     */
    public function __construct(
        public readonly ?string $mediaFormat = null,
        public readonly ?string $sourceUrl = null,
        public readonly ?string $googleUrl = null,
        public readonly array $raw = [],
    ) {
    }

    /**
     * Build a MediaItem from an API payload.
     *
     * Missing fields remain null. Unknown fields survive on
     * {@see self::$raw}.
     *
     * @since 1.0.0
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray( array $data ): self
    {
        $mediaFormat = null;

        if ( isset( $data['mediaFormat'] ) && '' !== $data['mediaFormat'] ) {
            $mediaFormat = (string) $data['mediaFormat'];
        }

        $sourceUrl = null;

        if ( isset( $data['sourceUrl'] ) && '' !== $data['sourceUrl'] ) {
            $sourceUrl = (string) $data['sourceUrl'];
        }

        $googleUrl = null;

        if ( isset( $data['googleUrl'] ) && '' !== $data['googleUrl'] ) {
            $googleUrl = (string) $data['googleUrl'];
        }

        return new self( $mediaFormat, $sourceUrl, $googleUrl, $data );
    }
}
