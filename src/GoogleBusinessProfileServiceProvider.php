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

use ArtisanPackUI\Google\Facades\Google;
use ArtisanPackUI\GoogleBusinessProfile\Scopes\Scopes;
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
     * Auto-registers the single OAuth scope every Google Business Profile
     * API surface requires ({@see Scopes::BUSINESS_MANAGE}) with the
     * {@see \ArtisanPackUI\Google\Scopes\ScopeRegistry} shipped by the
     * `artisanpack-ui/google` package. Downstream hosts get the scope
     * added to the OAuth consent request without any per-app wiring.
     *
     * The `class_exists()` guard keeps the package usable in hosts that
     * bind their own {@see \ArtisanPackUI\GoogleBusinessProfile\Contracts\TokenProvider}
     * without pulling in `artisanpack-ui/google`.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function boot(): void
    {
        if ( class_exists( Google::class ) ) {
            Google::scopes()->register( Scopes::BUSINESS_MANAGE );
        }
    }
}
