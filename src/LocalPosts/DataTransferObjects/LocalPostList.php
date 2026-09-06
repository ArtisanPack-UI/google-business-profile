<?php

/**
 * Paginated local post list DTO for the v4 Local Posts API.
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
 * Immutable, single-page result returned by
 * {@see \ArtisanPackUI\GoogleBusinessProfile\LocalPosts\LocalPostsClient::listLocalPosts()}.
 *
 * Wraps the array of {@see LocalPost} DTOs together with the pagination
 * token the API returns when more pages are available. `nextPageToken` is
 * null on the final page, matching the API's own contract of only emitting
 * the field when there is more to fetch.
 *
 * @package    ArtisanPack_UI
 * @subpackage GoogleBusinessProfile
 *
 * @since      1.0.0
 */
final class LocalPostList
{
    /**
     * Construct a LocalPostList.
     *
     * @since 1.0.0
     *
     * @param  list<LocalPost>  $localPosts  Local posts on this page.
     * @param  string|null  $nextPageToken  Token for fetching the next
     *                                      page, or null when this is the
     *                                      final page.
     */
    public function __construct(
        public readonly array $localPosts,
        public readonly ?string $nextPageToken = null,
    ) {
    }

    /**
     * Build a LocalPostList from a decoded `localPosts.list` response.
     *
     * A response with no `localPosts` key (or a non-array value there) is
     * treated as an empty page, matching how the API omits the key when the
     * location has no posts. Any entry that is not itself an associative
     * array is skipped so a malformed row does not poison the rest of the
     * page.
     *
     * @since 1.0.0
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray( array $data ): self
    {
        $rawPosts = $data['localPosts'] ?? [];

        $posts = [];

        if ( is_array( $rawPosts ) ) {
            foreach ( $rawPosts as $rawPost ) {
                if ( is_array( $rawPost ) && ! array_is_list( $rawPost ) ) {
                    $posts[] = LocalPost::fromArray( $rawPost );
                }
            }
        }

        $nextPageToken = null;

        if ( isset( $data['nextPageToken'] ) && '' !== $data['nextPageToken'] ) {
            $nextPageToken = (string) $data['nextPageToken'];
        }

        return new self( $posts, $nextPageToken );
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
