<?php

/**
 * Base exception for the Google Business Profile package.
 *
 * @package    ArtisanPack_UI
 * @subpackage GoogleBusinessProfile
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\GoogleBusinessProfile\Exceptions;

use RuntimeException;

/**
 * Marker base class for every exception thrown by this package.
 *
 * Callers can `catch ( GoogleBusinessProfileException $e )` to handle any
 * failure originating in this package without also swallowing unrelated
 * runtime exceptions.
 *
 * @package    ArtisanPack_UI
 * @subpackage GoogleBusinessProfile
 *
 * @since      1.0.0
 */
class GoogleBusinessProfileException extends RuntimeException
{
}
