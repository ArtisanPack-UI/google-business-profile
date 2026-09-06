<?php

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend( Tests\TestCase::class )
 // ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in( 'Feature' );

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend( 'toBeOne', function () {
    return $this->toBe( 1 );
} );

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something(): void
{
    // ..
}

/**
 * Load a JSON fixture from `tests/Fixtures/payloads/` and return it decoded.
 *
 * Path is relative to `tests/Fixtures/payloads/` (e.g. `reviews/reviews.list.full.json`).
 * The fixtures capture canonical Google Business Profile API responses so
 * the DTO-mapping tests can pin exact wire shapes without embedding them
 * inline in every test.
 *
 * @return array<string, mixed>
 */
function gbpFixture( string $path ): array
{
    $absolute = __DIR__ . '/Fixtures/payloads/' . ltrim( $path, '/' );

    if ( ! is_file( $absolute ) ) {
        throw new RuntimeException( sprintf( 'Fixture not found: %s', $absolute ) );
    }

    $contents = file_get_contents( $absolute );

    if ( false === $contents ) {
        throw new RuntimeException( sprintf( 'Unable to read fixture: %s', $absolute ) );
    }

    $decoded = json_decode( $contents, true, 512, JSON_THROW_ON_ERROR );

    if ( ! is_array( $decoded ) ) {
        throw new RuntimeException( sprintf( 'Fixture %s did not decode to an object.', $path ) );
    }

    return $decoded;
}

/**
 * Build any BaseClient-derived API client wired for fixture-driven tests.
 *
 * All fixture feature tests use the same wiring: the fixture-stub
 * TokenProvider, the shared HttpFactory so `Http::fake()` intercepts, a
 * 5-second timeout, and a single attempt with no sleep. Centralising here
 * keeps that shape in one place and avoids six near-identical factories.
 *
 * @template T of \ArtisanPackUI\GoogleBusinessProfile\Http\BaseClient
 *
 * @param  class-string<T>  $clientClass
 *
 * @return T
 */
function gbpFixtureClient( string $clientClass ): ArtisanPackUI\GoogleBusinessProfile\Http\BaseClient
{
    return new $clientClass(
        tokenProvider: new Tests\Fixtures\FixtureTokenProvider(),
        http         : app( Illuminate\Http\Client\Factory::class ),
        timeout      : 5,
        maxAttempts  : 1,
        retrySleepMs : 0,
    );
}
