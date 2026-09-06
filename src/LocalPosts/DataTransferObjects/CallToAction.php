<?php

/**
 * Call-to-action DTO for the Google Business Profile v4 Local Posts API.
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
 * Immutable representation of a local post's call-to-action button.
 *
 * Mirrors the `CallToAction` sub-resource on a v4 `LocalPost`. The
 * `actionType` field is a string enum documented by Google with values
 * `ACTION_TYPE_UNSPECIFIED`, `BOOK`, `ORDER`, `SHOP`, `LEARN_MORE`,
 * `SIGN_UP`, and `CALL`, but is preserved verbatim so callers see any
 * value Google may add later without the DTO layer silently dropping it.
 *
 * The `url` field is not required by the API for `CALL` action types, so
 * it is nullable here to match the wire shape.
 *
 * @package    ArtisanPack_UI
 * @subpackage GoogleBusinessProfile
 *
 * @since      1.0.0
 */
final class CallToAction
{
    /**
     * Construct a CallToAction DTO.
     *
     * @since 1.0.0
     *
     * @param  string|null  $actionType  Action type enum value (e.g. `BOOK`,
     *                                   `LEARN_MORE`), or null when the API
     *                                   omitted the field.
     * @param  string|null  $url  Destination URL, or null when the action
     *                            type does not require one (e.g. `CALL`).
     */
    public function __construct(
        public readonly ?string $actionType = null,
        public readonly ?string $url = null,
    ) {
    }

    /**
     * Build a CallToAction from an API payload.
     *
     * Missing fields remain null so a partial payload does not poison an
     * otherwise-usable local post.
     *
     * @since 1.0.0
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray( array $data ): self
    {
        $actionType = null;

        if ( isset( $data['actionType'] ) && '' !== $data['actionType'] ) {
            $actionType = (string) $data['actionType'];
        }

        $url = null;

        if ( isset( $data['url'] ) && '' !== $data['url'] ) {
            $url = (string) $data['url'];
        }

        return new self( $actionType, $url );
    }
}
