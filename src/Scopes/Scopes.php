<?php

/**
 * Google Business Profile OAuth scope constants.
 *
 * @package    ArtisanPack_UI
 * @subpackage GoogleBusinessProfile
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\GoogleBusinessProfile\Scopes;

/**
 * OAuth scope constants for the Google Business Profile API family.
 *
 * The package itself does not perform OAuth; these constants exist so the
 * host application (for example, the Keystone Google OAuth manager) can
 * request the correct scope when acquiring the access token that the
 * {@see \ArtisanPackUI\GoogleBusinessProfile\Contracts\TokenProvider}
 * implementation later hands to the client.
 *
 * @package    ArtisanPack_UI
 * @subpackage GoogleBusinessProfile
 *
 * @since      1.0.0
 */
final class Scopes
{
    /**
     * The single OAuth scope required to call every Google Business Profile
     * API surface (accounts, locations, verifications, reviews, posts,
     * Q&A, and performance).
     *
     * @since 1.0.0
     *
     * @var string
     */
    public const BUSINESS_MANAGE = 'https://www.googleapis.com/auth/business.manage';

    /**
     * Prevents instantiation; this class is a constant holder only.
     *
     * @since 1.0.0
     */
    private function __construct()
    {
    }
}
