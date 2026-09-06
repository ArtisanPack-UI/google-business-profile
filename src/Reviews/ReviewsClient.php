<?php

/**
 * Google Business Profile v4 legacy Reviews API client.
 *
 * @package    ArtisanPack_UI
 * @subpackage GoogleBusinessProfile
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\GoogleBusinessProfile\Reviews;

use ArtisanPackUI\GoogleBusinessProfile\Http\BaseClient;
use ArtisanPackUI\GoogleBusinessProfile\Reviews\DataTransferObjects\ReviewList;
use ArtisanPackUI\GoogleBusinessProfile\Reviews\DataTransferObjects\ReviewReply;
use InvalidArgumentException;

/**
 * Typed client for the Google Business Profile v4 legacy Reviews API.
 *
 * Reviews are one of the surfaces Google has not migrated off the v4
 * `mybusiness.googleapis.com` host, so this client is scoped to that
 * legacy base URL. Callers should not confuse it with the v1 Business
 * Information, Account Management, or Performance clients, which each
 * live on their own dedicated hosts.
 *
 * Exposes the two endpoints needed to power a review-management UI:
 *
 * - `accounts.locations.reviews.list` — enumerate the reviews for a
 *   location, returned as a {@see ReviewList} with the location-wide
 *   average rating and total review count on the same page.
 * - `accounts.locations.reviews.updateReply` — upsert the merchant
 *   reply to a single review, returned as a {@see ReviewReply}.
 *
 * Every request rides the shared {@see BaseClient} plumbing: a Bearer
 * token fetched from the injected `TokenProvider`, JSON accept headers,
 * retry on `429`/`5xx`, and mapping of errors to
 * {@see \ArtisanPackUI\GoogleBusinessProfile\Exceptions\ApiException}.
 *
 * @package    ArtisanPack_UI
 * @subpackage GoogleBusinessProfile
 *
 * @since      1.0.0
 */
class ReviewsClient extends BaseClient
{
    /**
     * Google's documented maximum for the `pageSize` parameter on
     * `accounts.locations.reviews.list`. Requests larger than this are
     * rejected by the API, so the caller validates against this ceiling
     * and throws {@see InvalidArgumentException} to fail loudly during
     * development rather than after a network round-trip.
     *
     * @since 1.0.0
     *
     * @var int
     */
    public const MAX_PAGE_SIZE = 50;

    /**
     * List the reviews attached to a single location.
     *
     * Returns a single page. When {@see ReviewList::$nextPageToken} is
     * non-null the caller should re-invoke this method with that token
     * to fetch the next page. The `averageRating` and `totalReviewCount`
     * fields on the response are location-wide aggregates and therefore
     * repeat on every page — they are not per-page totals.
     *
     * @since 1.0.0
     *
     * @param  string  $parent  Parent location resource name
     *                          (`accounts/{accountId}/locations/{locationId}`).
     * @param  int|null  $pageSize  Requested page size (1-{@see self::MAX_PAGE_SIZE}).
     *                              Null omits the parameter and lets the
     *                              API pick its default.
     * @param  string|null  $pageToken  Token returned by a prior call's
     *                                  `nextPageToken`, or null for the
     *                                  first page.
     * @param  string|null  $orderBy  Optional sort expression documented
     *                                by the API (e.g. `updateTime desc`,
     *                                `rating`, `rating desc`), or null to
     *                                accept the API's default ordering.
     *
     * @throws InvalidArgumentException When `parent` is empty or
     *                                  `pageSize` is outside the
     *                                  documented 1-{@see self::MAX_PAGE_SIZE}
     *                                  range.
     * @throws \ArtisanPackUI\GoogleBusinessProfile\Exceptions\ApiException
     *         When the API returns an error or the request fails at the
     *         transport level after all retries.
     */
    public function listReviews(
        string $parent,
        ?int $pageSize = null,
        ?string $pageToken = null,
        ?string $orderBy = null,
    ): ReviewList {
        if ( '' === $parent ) {
            throw new InvalidArgumentException( 'parent is required and must be a non-empty location resource name.' );
        }

        $query = [];

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

        if ( null !== $orderBy && '' !== $orderBy ) {
            $query['orderBy'] = $orderBy;
        }

        $body = $this->request( 'GET', $parent . '/reviews', $query );

        return ReviewList::fromArray( $body );
    }

    /**
     * Upsert the merchant reply to a single review.
     *
     * The v4 Reviews API models reply creation and reply update as the
     * same `PUT` on the review's `/reply` sub-resource, so this single
     * method covers both cases. Posting a new comment replaces any
     * existing reply verbatim; there is no partial update.
     *
     * @since 1.0.0
     *
     * @param  string  $name  Review resource name
     *                        (`accounts/{a}/locations/{l}/reviews/{r}`).
     * @param  string  $comment  Reply text. Trimmed of surrounding
     *                           whitespace; the trimmed value must be
     *                           non-empty, otherwise the API rejects the
     *                           request with a validation error.
     *
     * @throws InvalidArgumentException When `name` is empty or `comment`
     *                                  is empty after trimming.
     * @throws \ArtisanPackUI\GoogleBusinessProfile\Exceptions\ApiException
     *         When the API returns an error or the request fails at the
     *         transport level after all retries.
     */
    public function replyToReview( string $name, string $comment ): ReviewReply
    {
        if ( '' === $name ) {
            throw new InvalidArgumentException( 'name is required and must be a non-empty review resource name.' );
        }

        $trimmed = trim( $comment );

        if ( '' === $trimmed ) {
            throw new InvalidArgumentException( 'comment is required and must be a non-empty reply body.' );
        }

        $body = $this->request( 'PUT', $name . '/reply', [], [ 'comment' => $trimmed ] );

        return ReviewReply::fromArray( $body );
    }

    /**
     * Base URL for the Google Business Profile v4 legacy surface.
     *
     * @since 1.0.0
     */
    protected function baseUrl(): string
    {
        return 'https://mybusiness.googleapis.com/v4';
    }
}
