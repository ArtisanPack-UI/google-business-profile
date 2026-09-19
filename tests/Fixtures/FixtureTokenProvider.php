<?php

/**
 * Shared TokenProvider stub for fixture-driven Http::fake() tests.
 *
 * @package    ArtisanPack_UI
 * @subpackage GoogleBusinessProfile
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace Tests\Fixtures;

use ArtisanPackUI\GoogleBusinessProfile\Contracts\TokenProvider;

/**
 * Constant-token TokenProvider used by fixture-driven feature tests.
 *
 * Kept out of individual test files so every API-family fixture test
 * asserts the same `Authorization: Bearer fixture-token` header without
 * redefining the stub locally.
 *
 * @since 1.0.0
 */
final class FixtureTokenProvider implements TokenProvider
{
    public const TOKEN = 'fixture-token';

    public function accessToken(): string
    {
        return self::TOKEN;
    }
}
