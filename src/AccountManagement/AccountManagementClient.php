<?php

/**
 * Google Business Profile Account Management API client.
 *
 * @package    ArtisanPack_UI
 * @subpackage GoogleBusinessProfile
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\GoogleBusinessProfile\AccountManagement;

use ArtisanPackUI\GoogleBusinessProfile\AccountManagement\DataTransferObjects\AccountList;
use ArtisanPackUI\GoogleBusinessProfile\Http\BaseClient;
use InvalidArgumentException;

/**
 * Typed client for the Google Business Profile Account Management API.
 *
 * Exposes the endpoints needed to enumerate the accounts the caller can
 * manage. Every request rides the shared {@see BaseClient} plumbing:
 * a Bearer token fetched from the injected `TokenProvider`, JSON accept
 * headers, retry on `429`/`5xx`, and mapping of errors to
 * {@see \ArtisanPackUI\GoogleBusinessProfile\Exceptions\ApiException}.
 *
 * The Account Management API lives on its own host separate from the
 * Business Information, Performance, and legacy v4 surfaces, so this
 * client is scoped to just that host and returns typed DTOs rather than
 * raw arrays.
 *
 * @package    ArtisanPack_UI
 * @subpackage GoogleBusinessProfile
 *
 * @since      1.0.0
 */
class AccountManagementClient extends BaseClient
{
    /**
     * Google's documented maximum for the `pageSize` parameter on
     * `accounts.list`. Requests larger than this are rejected by the API,
     * so we clamp in the caller to fail loudly during development rather
     * than after a network round-trip.
     *
     * @since 1.0.0
     *
     * @var int
     */
    public const MAX_PAGE_SIZE = 20;

    /**
     * List the accounts the authenticated caller can manage.
     *
     * Returns a single page. When {@see AccountList::$nextPageToken} is
     * non-null the caller should re-invoke this method with that token
     * to fetch the next page.
     *
     * @since 1.0.0
     *
     * @param  int|null  $pageSize  Requested page size (1-{@see self::MAX_PAGE_SIZE}).
     *                              Null omits the parameter and lets the
     *                              API pick its default.
     * @param  string|null  $pageToken  Token returned by a prior call's
     *                                  `nextPageToken`, or null for the
     *                                  first page.
     * @param  string|null  $filter  Optional API-side filter expression
     *                               (see Google's docs for supported
     *                               fields), or null to omit.
     * @param  string|null  $parentAccount  Optional parent account
     *                                      resource name (e.g.
     *                                      `accounts/{accountId}`) to
     *                                      list only child accounts of
     *                                      that organization; null lists
     *                                      the caller's own accounts.
     *
     * @throws InvalidArgumentException When `pageSize` is outside the
     *                                  documented 1-{@see self::MAX_PAGE_SIZE}
     *                                  range.
     * @throws \ArtisanPackUI\GoogleBusinessProfile\Exceptions\ApiException
     *         When the API returns an error or the request fails at the
     *         transport level after all retries.
     */
    public function listAccounts(
        ?int $pageSize = null,
        ?string $pageToken = null,
        ?string $filter = null,
        ?string $parentAccount = null,
    ): AccountList {
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

        if ( null !== $filter && '' !== $filter ) {
            $query['filter'] = $filter;
        }

        if ( null !== $parentAccount && '' !== $parentAccount ) {
            $query['parentAccount'] = $parentAccount;
        }

        $body = $this->request( 'GET', 'accounts', $query );

        return AccountList::fromArray( $body );
    }

    /**
     * Base URL for the Account Management API v1 surface.
     *
     * @since 1.0.0
     */
    protected function baseUrl(): string
    {
        return 'https://mybusinessaccountmanagement.googleapis.com/v1';
    }
}
