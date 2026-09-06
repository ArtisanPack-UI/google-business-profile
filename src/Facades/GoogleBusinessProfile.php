<?php

/**
 * GoogleBusinessProfile Facade.
 *
 * Provides static access to the GoogleBusinessProfile class.
 *
 * @package    ArtisanPack_UI
 * @subpackage GoogleBusinessProfile
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\GoogleBusinessProfile\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * GoogleBusinessProfile Facade.
 *
 * @see \ArtisanPackUI\GoogleBusinessProfile\GoogleBusinessProfile
 *
 * @package    ArtisanPack_UI
 * @subpackage GoogleBusinessProfile
 *
 * @since      1.0.0
 */
class GoogleBusinessProfile extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @since 1.0.0
     *
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return 'google-business-profile';
    }
}
