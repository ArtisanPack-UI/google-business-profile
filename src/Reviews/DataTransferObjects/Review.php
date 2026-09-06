<?php

/**
 * Review DTO for the Google Business Profile v4 Reviews API.
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
 * Immutable representation of a single Google Business Profile review.
 *
 * Mirrors the `Review` resource returned by the v4 Reviews API's
 * `accounts.locations.reviews.list` endpoint. Enum-shaped fields (star
 * rating) are kept as plain strings so callers see whatever value the
 * API returned — including values Google may add later — without the
 * DTO layer silently dropping them.
 *
 * The API's `starRating` field is a string enum with the documented
 * values `STAR_RATING_UNSPECIFIED`, `ONE`, `TWO`, `THREE`, `FOUR`, and
 * `FIVE`. Callers that need a numeric rating can use {@see numericRating()}
 * which maps these strings to `1..5` (returning `0` for any other value,
 * including the unspecified case).
 *
 * The full review payload — every field the API returned, whether or not
 * it also appears as a typed property — is preserved on {@see self::$raw}
 * so consumers can reach fields the typed surface does not yet expose
 * without a round-trip. Because typed fields are duplicated on `$raw`,
 * treat it as the source of truth for the wire payload rather than as a
 * "leftovers" bag.
 *
 * @package    ArtisanPack_UI
 * @subpackage GoogleBusinessProfile
 *
 * @since      1.0.0
 */
final class Review
{
    /**
     * Map of the v4 `starRating` enum values to their numeric equivalent.
     *
     * @since 1.0.0
     *
     * @var array<string, int>
     */
    private const STAR_RATING_MAP = [
        'ONE'   => 1,
        'TWO'   => 2,
        'THREE' => 3,
        'FOUR'  => 4,
        'FIVE'  => 5,
    ];

    /**
     * Construct a Review DTO.
     *
     * @since 1.0.0
     *
     * @param  string  $name  Resource name
     *                        (`accounts/{a}/locations/{l}/reviews/{r}`).
     * @param  string  $reviewId  Short review identifier.
     * @param  Reviewer|null  $reviewer  The review's author, or null when
     *                                   the API omitted the sub-resource.
     * @param  string|null  $starRating  Star rating enum value (e.g.
     *                                   `FIVE`), or null when absent.
     * @param  string|null  $comment  Review comment text, or null when
     *                                the reviewer left a rating only.
     * @param  string|null  $createTime  RFC 3339 timestamp for when the
     *                                   review was created, or null.
     * @param  string|null  $updateTime  RFC 3339 timestamp for the last
     *                                   review edit, or null.
     * @param  ReviewReply|null  $reviewReply  The merchant reply, or null
     *                                         when no reply has been
     *                                         posted.
     * @param  array<string, mixed>  $raw  The raw payload for this review
     *                                     exactly as returned by the API.
     */
    public function __construct(
        public readonly string $name,
        public readonly string $reviewId,
        public readonly ?Reviewer $reviewer = null,
        public readonly ?string $starRating = null,
        public readonly ?string $comment = null,
        public readonly ?string $createTime = null,
        public readonly ?string $updateTime = null,
        public readonly ?ReviewReply $reviewReply = null,
        public readonly array $raw = [],
    ) {
    }

    /**
     * Build a Review from an API payload.
     *
     * Missing fields are coerced to sensible defaults rather than
     * throwing: only `name` and `reviewId` are guaranteed by the API for
     * a listed review, and neither the reviewer sub-resource nor the
     * merchant reply is emitted when absent. Unknown fields survive on
     * {@see self::$raw}.
     *
     * @since 1.0.0
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray( array $data ): self
    {
        $reviewer = null;

        if ( isset( $data['reviewer'] ) && is_array( $data['reviewer'] ) ) {
            $reviewer = Reviewer::fromArray( $data['reviewer'] );
        }

        $reviewReply = null;

        if ( isset( $data['reviewReply'] ) && is_array( $data['reviewReply'] ) ) {
            $reviewReply = ReviewReply::fromArray( $data['reviewReply'] );
        }

        return new self(
            name       : isset( $data['name'] ) ? (string) $data['name'] : '',
            reviewId   : isset( $data['reviewId'] ) ? (string) $data['reviewId'] : '',
            reviewer   : $reviewer,
            starRating : isset( $data['starRating'] ) ? (string) $data['starRating'] : null,
            comment    : isset( $data['comment'] ) ? (string) $data['comment'] : null,
            createTime : isset( $data['createTime'] ) ? (string) $data['createTime'] : null,
            updateTime : isset( $data['updateTime'] ) ? (string) $data['updateTime'] : null,
            reviewReply: $reviewReply,
            raw        : $data,
        );
    }

    /**
     * Whether the merchant has posted a reply to this review.
     *
     * @since 1.0.0
     */
    public function hasReply(): bool
    {
        return null !== $this->reviewReply;
    }

    /**
     * Map the string `starRating` enum to its `1..5` numeric equivalent.
     *
     * Returns `0` when the field is null, unspecified, or a value the
     * API added after this map was last updated. Callers that need to
     * distinguish "unrated" from "one star" should inspect
     * {@see self::$starRating} directly.
     *
     * @since 1.0.0
     */
    public function numericRating(): int
    {
        if ( null === $this->starRating ) {
            return 0;
        }

        return self::STAR_RATING_MAP[ $this->starRating ] ?? 0;
    }
}
