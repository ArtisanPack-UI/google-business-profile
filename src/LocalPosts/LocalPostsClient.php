<?php

/**
 * Google Business Profile v4 legacy Local Posts API client.
 *
 * @package    ArtisanPack_UI
 * @subpackage GoogleBusinessProfile
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\GoogleBusinessProfile\LocalPosts;

use ArtisanPackUI\GoogleBusinessProfile\Http\BaseClient;
use ArtisanPackUI\GoogleBusinessProfile\LocalPosts\DataTransferObjects\LocalPost;
use ArtisanPackUI\GoogleBusinessProfile\LocalPosts\DataTransferObjects\LocalPostList;
use InvalidArgumentException;

/**
 * Typed client for the Google Business Profile v4 legacy Local Posts API.
 *
 * Local posts (Google's "What's New", "Event", "Offer", and "Alert" post
 * types) are one of the surfaces Google has not migrated off the v4
 * `mybusiness.googleapis.com` host, so this client is scoped to that
 * legacy base URL. Callers should not confuse it with the v1 Business
 * Information, Account Management, or Performance clients, which each
 * live on their own dedicated hosts.
 *
 * Exposes the three endpoints needed to power a post-management UI:
 *
 * - `accounts.locations.localPosts.create` — publish a new post against a
 *   location, returned as a {@see LocalPost}.
 * - `accounts.locations.localPosts.list` — enumerate the posts attached to
 *   a location, returned as a {@see LocalPostList}.
 * - `accounts.locations.localPosts.delete` — permanently remove a post by
 *   its resource name.
 *
 * The `create` method takes the post body as an associative array rather
 * than a typed builder: the v4 payload is a wide, sparsely-populated shape
 * (topic-specific fields for `EVENT`/`OFFER`/`ALERT` posts, plus a media
 * list) and building typed setters for every field would obscure the wire
 * shape without meaningfully constraining callers. The returned DTOs
 * remain typed so downstream consumers still see structured data.
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
class LocalPostsClient extends BaseClient
{
    /**
     * Google's documented maximum for the `pageSize` parameter on
     * `accounts.locations.localPosts.list`. Requests larger than this are
     * rejected by the API, so the caller validates against this ceiling
     * and throws {@see InvalidArgumentException} to fail loudly during
     * development rather than after a network round-trip.
     *
     * @since 1.0.0
     *
     * @var int
     */
    public const MAX_PAGE_SIZE = 100;

    /**
     * Create a new local post attached to a location.
     *
     * The `$post` payload is sent verbatim as the JSON body and must be a
     * non-empty associative array shaped like a v4 `LocalPost` resource
     * (`summary`, `languageCode`, `topicType`, optional `callToAction`,
     * `event`, `offer`, `alertType`, and `media` list). Google enforces
     * topic-specific field requirements server-side (for example,
     * `EVENT` posts require an `event.schedule`) so this method does not
     * duplicate that validation client-side; instead a rejected payload
     * surfaces as an {@see \ArtisanPackUI\GoogleBusinessProfile\Exceptions\ApiException}.
     *
     * @since 1.0.0
     *
     * @param  string  $parent  Parent location resource name
     *                          (`accounts/{accountId}/locations/{locationId}`).
     * @param  array<string, mixed>  $post  Post payload shaped like a v4
     *                                      `LocalPost` resource.
     *
     * @throws InvalidArgumentException When `parent` is empty or `post`
     *                                  is an empty array.
     * @throws \ArtisanPackUI\GoogleBusinessProfile\Exceptions\ApiException
     *         When the API returns an error or the request fails at the
     *         transport level after all retries.
     */
    public function createLocalPost( string $parent, array $post ): LocalPost
    {
        if ( '' === $parent ) {
            throw new InvalidArgumentException( 'parent is required and must be a non-empty location resource name.' );
        }

        if ( [] === $post ) {
            throw new InvalidArgumentException( 'post is required and must be a non-empty local post payload.' );
        }

        $body = $this->request( 'POST', $parent . '/localPosts', [], $post );

        return LocalPost::fromArray( $body );
    }

    /**
     * List the local posts attached to a single location.
     *
     * Returns a single page. When {@see LocalPostList::$nextPageToken} is
     * non-null the caller should re-invoke this method with that token to
     * fetch the next page.
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
     *
     * @throws InvalidArgumentException When `parent` is empty or
     *                                  `pageSize` is outside the
     *                                  documented 1-{@see self::MAX_PAGE_SIZE}
     *                                  range.
     * @throws \ArtisanPackUI\GoogleBusinessProfile\Exceptions\ApiException
     *         When the API returns an error or the request fails at the
     *         transport level after all retries.
     */
    public function listLocalPosts(
        string $parent,
        ?int $pageSize = null,
        ?string $pageToken = null,
    ): LocalPostList {
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

        $body = $this->request( 'GET', $parent . '/localPosts', $query );

        return LocalPostList::fromArray( $body );
    }

    /**
     * Permanently delete a local post by its resource name.
     *
     * The API returns an empty body on success; this method therefore
     * returns void and relies on the shared error mapping to raise on any
     * non-2xx response. Deletion is unconditional — the API has no soft
     * delete — so callers should confirm intent before invoking.
     *
     * @since 1.0.0
     *
     * @param  string  $name  Local post resource name
     *                        (`accounts/{a}/locations/{l}/localPosts/{p}`).
     *
     * @throws InvalidArgumentException When `name` is empty.
     * @throws \ArtisanPackUI\GoogleBusinessProfile\Exceptions\ApiException
     *         When the API returns an error or the request fails at the
     *         transport level after all retries.
     */
    public function deleteLocalPost( string $name ): void
    {
        if ( '' === $name ) {
            throw new InvalidArgumentException( 'name is required and must be a non-empty local post resource name.' );
        }

        $this->request( 'DELETE', $name );
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
