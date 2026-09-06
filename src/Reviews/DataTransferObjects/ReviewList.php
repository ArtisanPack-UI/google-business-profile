<?php

/**
 * Paginated review list DTO for the v4 Reviews API.
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
 * Immutable, single-page result returned by
 * {@see \ArtisanPackUI\GoogleBusinessProfile\Reviews\ReviewsClient::listReviews()}.
 *
 * Wraps the array of {@see Review} DTOs together with the pagination token
 * the API returns when more pages are available and the location-wide
 * aggregate figures (`averageRating`, `totalReviewCount`) that the v4
 * Reviews API emits on every page. `nextPageToken` is null on the final
 * page, matching the API's own contract of only emitting the field when
 * there is more to fetch.
 *
 * @package    ArtisanPack_UI
 * @subpackage GoogleBusinessProfile
 *
 * @since      1.0.0
 */
final class ReviewList
{
    /**
     * Construct a ReviewList.
     *
     * @since 1.0.0
     *
     * @param  list<Review>  $reviews  Reviews on this page.
     * @param  float|null  $averageRating  Location-wide average star
     *                                     rating (`1.0..5.0`), or null
     *                                     when the API omitted the field.
     * @param  int|null  $totalReviewCount  Location-wide total number of
     *                                      reviews, or null when the API
     *                                      omitted the field.
     * @param  string|null  $nextPageToken  Token for fetching the next
     *                                      page, or null when this is the
     *                                      final page.
     */
    public function __construct(
        public readonly array $reviews,
        public readonly ?float $averageRating = null,
        public readonly ?int $totalReviewCount = null,
        public readonly ?string $nextPageToken = null,
    ) {
    }

    /**
     * Build a ReviewList from a decoded `reviews.list` response.
     *
     * A response with no `reviews` key (or a non-array value there) is
     * treated as an empty page, matching how the API omits the key when
     * the location has no reviews. Any entry that is not itself an
     * associative array is skipped so a malformed row does not poison
     * the rest of the page.
     *
     * @since 1.0.0
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray( array $data ): self
    {
        $rawReviews = $data['reviews'] ?? [];

        $reviews = [];

        if ( is_array( $rawReviews ) ) {
            foreach ( $rawReviews as $rawReview ) {
                if ( is_array( $rawReview ) ) {
                    $reviews[] = Review::fromArray( $rawReview );
                }
            }
        }

        $averageRating = null;

        if ( isset( $data['averageRating'] ) && is_numeric( $data['averageRating'] ) ) {
            $averageRating = (float) $data['averageRating'];
        }

        $totalReviewCount = null;

        if ( isset( $data['totalReviewCount'] ) && is_numeric( $data['totalReviewCount'] ) ) {
            $totalReviewCount = (int) $data['totalReviewCount'];
        }

        $nextPageToken = null;

        if ( isset( $data['nextPageToken'] ) && '' !== $data['nextPageToken'] ) {
            $nextPageToken = (string) $data['nextPageToken'];
        }

        return new self( $reviews, $averageRating, $totalReviewCount, $nextPageToken );
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
