<?php

/**
 * MediaItemDataRef DTO for the Google Business Profile v4 Media API.
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
 * Immutable handle returned by `accounts.locations.media:startUpload`.
 *
 * A `MediaItemDataRef` is Google's opaque pointer to a byte upload slot. The
 * caller receives the {@see self::$resourceName} from the start-upload call,
 * uploads the media bytes to the `/upload/v1/media/{resourceName}` endpoint,
 * then creates the {@see MediaItem} with the same `dataRef.resourceName` set
 * in the create-media payload. Google associates the two out of band, so the
 * resource name must be forwarded verbatim.
 *
 * The full payload — every field the API returned — is preserved on
 * {@see self::$raw} so consumers can reach fields the typed surface does not
 * yet expose without a round-trip.
 *
 * @package    ArtisanPack_UI
 * @subpackage GoogleBusinessProfile
 *
 * @since      1.0.0
 */
final class MediaItemDataRef
{
    /**
     * Construct a MediaItemDataRef DTO.
     *
     * @since 1.0.0
     *
     * @param  string  $resourceName  Google-assigned upload slot identifier
     *                                returned by `media:startUpload`. Sent
     *                                back to the byte-upload endpoint as
     *                                part of the URL and to `media.create`
     *                                as `dataRef.resourceName`.
     * @param  array<string, mixed>  $raw  The raw payload for this data ref
     *                                     exactly as returned by the API.
     */
    public function __construct(
        public readonly string $resourceName,
        public readonly array $raw = [],
    ) {
    }

    /**
     * Build a MediaItemDataRef from an API payload.
     *
     * A missing `resourceName` is coerced to an empty string rather than
     * throwing so the caller can decide how to react; the {@see MediaClient}
     * refuses to upload bytes against an empty resource name.
     *
     * @since 1.0.0
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray( array $data ): self
    {
        return new self(
            resourceName: isset( $data['resourceName'] ) ? (string) $data['resourceName'] : '',
            raw         : $data,
        );
    }
}
