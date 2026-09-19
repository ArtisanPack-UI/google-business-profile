<?php

/**
 * Reviewer DTO for the Google Business Profile v4 Reviews API.
 *
 * @package    ArtisanPack_UI
 * @subpackage GoogleBusinessProfile
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\GoogleBusinessProfile\Reviews\DataTransferObjects;

/**
 * Immutable representation of the author of a Google Business Profile
 * review.
 *
 * Mirrors the `Reviewer` sub-resource returned inline on a review by the
 * v4 Reviews API. Google may omit `displayName` and `profilePhotoUrl` when
 * a review is left anonymously, so both fields fall back to null rather
 * than empty strings so callers can distinguish "no value" from "empty
 * string".
 *
 * @package    ArtisanPack_UI
 * @subpackage GoogleBusinessProfile
 *
 * @since      1.0.0
 */
final class Reviewer
{
    /**
     * Construct a Reviewer DTO.
     *
     * @since 1.0.0
     *
     * @param  string|null  $displayName  Public display name for the
     *                                    reviewer, or null when the
     *                                    review is anonymous or the API
     *                                    omitted the field.
     * @param  string|null  $profilePhotoUrl  URL of the reviewer's public
     *                                        profile photo, or null when
     *                                        absent.
     * @param  bool  $isAnonymous  Whether the reviewer posted the review
     *                             anonymously.
     */
    public function __construct(
        public readonly ?string $displayName = null,
        public readonly ?string $profilePhotoUrl = null,
        public readonly bool $isAnonymous = false,
    ) {
    }

    /**
     * Build a Reviewer from an API payload.
     *
     * Missing fields are coerced to null so anonymous reviews (which
     * commonly omit `displayName` and `profilePhotoUrl`) still hydrate
     * cleanly.
     *
     * @since 1.0.0
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray( array $data ): self
    {
        $displayName = null;

        if ( isset( $data['displayName'] ) && '' !== $data['displayName'] ) {
            $displayName = (string) $data['displayName'];
        }

        $profilePhotoUrl = null;

        if ( isset( $data['profilePhotoUrl'] ) && '' !== $data['profilePhotoUrl'] ) {
            $profilePhotoUrl = (string) $data['profilePhotoUrl'];
        }

        return new self(
            displayName    : $displayName,
            profilePhotoUrl: $profilePhotoUrl,
            isAnonymous    : (bool) ( $data['isAnonymous'] ?? false ),
        );
    }
}
