<?php

/**
 * GoogleBusinessProfile helper functions.
 *
 * This file contains global helper functions for the package.
 * Add your custom helper functions below.
 *
 * @package    ArtisanPack_UI
 * @subpackage GoogleBusinessProfile
 *
 * @since      1.0.0
 */

use ArtisanPackUI\GoogleBusinessProfile\GoogleBusinessProfile;

if ( ! function_exists( 'googleBusinessProfile' ) ) {
    /**
     * Get the GoogleBusinessProfile instance.
     *
     * @since 1.0.0
     *
     * @return GoogleBusinessProfile
     */
    function googleBusinessProfile(): GoogleBusinessProfile
    {
        return app( 'google-business-profile' );
    }
}

// Add your custom helper functions below
