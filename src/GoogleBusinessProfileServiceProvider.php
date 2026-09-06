<?php

/**
 * GoogleBusinessProfile service provider.
 *
 * Bootstraps the GoogleBusinessProfile package by registering services and bindings.
 *
 * @package    ArtisanPack_UI
 * @subpackage GoogleBusinessProfile
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\GoogleBusinessProfile;

use Illuminate\Support\ServiceProvider;

/**
 * Service provider for the GoogleBusinessProfile package.
 *
 * Bootstraps the GoogleBusinessProfile package by registering services and
 * bindings. Extend this class with your package's configuration, migrations,
 * routes, views, and other service registrations.
 *
 * @package    ArtisanPack_UI
 * @subpackage GoogleBusinessProfile
 *
 * @since      1.0.0
 */
class GoogleBusinessProfileServiceProvider extends ServiceProvider
{
    /**
     * Registers any application services.
     *
     * Binds the GoogleBusinessProfile class as a singleton in the container.
     * Add additional service registrations here.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function register(): void
    {
        $this->app->singleton( 'google-business-profile', function ( $app ) {
            return new GoogleBusinessProfile();
        } );
    }

    /**
     * Bootstraps any application services.
     *
     * Add package bootstrapping here such as:
     * - Configuration publishing: $this->publishes([...])
     * - Migration loading: $this->loadMigrationsFrom(...)
     * - View loading: $this->loadViewsFrom(...)
     * - Route loading: $this->loadRoutesFrom(...)
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function boot(): void
    {
        // Add your package bootstrapping here
    }
}
