<?php

/**
 * Review reply DTO for the Google Business Profile v4 Reviews API.
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
 * Immutable representation of a merchant reply to a Google Business
 * Profile review.
 *
 * Mirrors the `ReviewReply` sub-resource returned inline on a review by
 * the v4 Reviews API, and also the standalone body returned by the
 * `reviews.updateReply` endpoint. `updateTime` is emitted by the API as
 * an RFC 3339 timestamp string; it is preserved verbatim rather than
 * parsed into a `DateTimeImmutable` so callers can decide how to handle
 * the value (parse it, log it, or forward it to the UI unchanged).
 *
 * @package    ArtisanPack_UI
 * @subpackage GoogleBusinessProfile
 *
 * @since      1.0.0
 */
final class ReviewReply
{
    /**
     * Construct a ReviewReply DTO.
     *
     * @since 1.0.0
     *
     * @param  string  $comment  The reply text as posted by the merchant.
     * @param  string|null  $updateTime  RFC 3339 timestamp of the last
     *                                   reply update, or null when the
     *                                   API omitted the field.
     */
    public function __construct(
        public readonly string $comment,
        public readonly ?string $updateTime = null,
    ) {
    }

    /**
     * Build a ReviewReply from an API payload.
     *
     * Missing `comment` is coerced to an empty string rather than throwing
     * so a partial payload does not poison an otherwise-usable review
     * list; missing `updateTime` remains null.
     *
     * @since 1.0.0
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray( array $data ): self
    {
        $updateTime = null;

        if ( isset( $data['updateTime'] ) && '' !== $data['updateTime'] ) {
            $updateTime = (string) $data['updateTime'];
        }

        return new self(
            comment   : isset( $data['comment'] ) ? (string) $data['comment'] : '',
            updateTime: $updateTime,
        );
    }
}
