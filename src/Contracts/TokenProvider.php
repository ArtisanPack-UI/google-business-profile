<?php

/**
 * TokenProvider contract.
 *
 * Contract implemented by any service that can supply a valid Google OAuth
 * access token for the Google Business Profile API client. The client itself
 * performs no OAuth — it delegates token acquisition to whichever provider
 * the host application binds (in Keystone: GoogleOAuthManager; in tests: a
 * stub; elsewhere: whatever the host provides).
 *
 * @package    ArtisanPack_UI
 * @subpackage GoogleBusinessProfile
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\GoogleBusinessProfile\Contracts;

/**
 * TokenProvider contract.
 *
 * @package    ArtisanPack_UI
 * @subpackage GoogleBusinessProfile
 *
 * @since      1.0.0
 */
interface TokenProvider
{
    /**
     * Return a valid Google OAuth access token to use as the Bearer credential
     * on Google Business Profile API requests.
     *
     * Implementations are responsible for refreshing expired tokens before
     * returning; the client will use the returned string verbatim.
     *
     * @since 1.0.0
     *
     * @return string A non-empty OAuth access token.
     */
    public function accessToken(): string;
}
