<?php

/**
 * Shared HTTP client foundation for the Google Business Profile package.
 *
 * @package    ArtisanPack_UI
 * @subpackage GoogleBusinessProfile
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\GoogleBusinessProfile\Http;

use ArtisanPackUI\GoogleBusinessProfile\Contracts\TokenProvider;
use ArtisanPackUI\GoogleBusinessProfile\Exceptions\ApiException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Response;

/**
 * Base class for every resource-specific Google Business Profile client.
 *
 * Responsibilities:
 *
 * - Fetch a fresh access token from the injected {@see TokenProvider} on
 *   every request (delegation only; no OAuth lives here).
 * - Attach the token as a Bearer credential and set JSON accept/content
 *   headers on the outgoing request.
 * - Retry idempotent-looking transport failures, `HTTP 429`, and `HTTP 5xx`
 *   responses up to the configured attempt count with a fixed sleep.
 * - Map every non-2xx response and every {@see ConnectionException} to an
 *   {@see ApiException} carrying the status code and raw body.
 *
 * The client uses the injected {@see HttpFactory}, which is the same
 * singleton the `Http` facade resolves; tests can therefore call
 * `Http::fake()` and record/stub requests without extra wiring.
 *
 * Subclasses only need to implement {@see baseUrl()} to point at their
 * API surface (for example, `https://mybusinessbusinessinformation.googleapis.com/v1`).
 *
 * @package    ArtisanPack_UI
 * @subpackage GoogleBusinessProfile
 *
 * @since      1.0.0
 */
abstract class BaseClient
{
    /**
     * Construct a base client.
     *
     * @since 1.0.0
     *
     * @param  TokenProvider  $tokenProvider  Supplies a valid OAuth access
     *                                        token on every request.
     * @param  HttpFactory  $http  The Laravel HTTP client factory. Injecting
     *                             the factory keeps the client compatible
     *                             with `Http::fake()`.
     * @param  int  $timeout  Per-request timeout, in seconds.
     * @param  int  $maxAttempts  Total number of attempts (retries + 1).
     *                            A value below 1 is coerced to 1.
     * @param  int  $retrySleepMs  Sleep between retry attempts, in
     *                             milliseconds.
     */
    public function __construct(
        protected TokenProvider $tokenProvider,
        protected HttpFactory $http,
        protected int $timeout = 30,
        protected int $maxAttempts = 3,
        protected int $retrySleepMs = 250,
    ) {
    }

    /**
     * The absolute base URL for the resource-specific API surface.
     *
     * Trailing slashes are tolerated; the request path is joined with a
     * single separating slash.
     *
     * @since 1.0.0
     */
    abstract protected function baseUrl(): string;

    /**
     * Send a request and return the decoded JSON body as an associative
     * array (or an empty array when the response has no body).
     *
     * @since 1.0.0
     *
     * @param  string  $method  HTTP verb (GET, POST, PATCH, DELETE, PUT).
     * @param  string  $path  Path relative to {@see baseUrl()}.
     * @param  array<string, mixed>  $query  Query-string parameters.
     * @param  array<string, mixed>|null  $json  JSON body for write verbs;
     *                                           null for verbs without a
     *                                           body.
     *
     * @throws ApiException When the request fails after all retry attempts,
     *                      or when the API returns a non-2xx status.
     *
     * @return array<string, mixed>
     */
    protected function request(
        string $method,
        string $path,
        array $query = [],
        ?array $json = null,
    ): array {
        $url         = $this->buildUrl( $path );
        $method      = strtoupper( $method );
        $maxAttempts = max( 1, $this->maxAttempts );

        // The loop always exits via `break`, `return`, or `throw` — a
        // transport failure on the final attempt throws inside the catch,
        // and every other path assigns $response before breaking.
        $response = null;

        for ( $attempt = 1; $attempt <= $maxAttempts; $attempt++ ) {
            try {
                $response = $this->dispatch( $method, $url, $query, $json );
            } catch ( ConnectionException $exception ) {
                if ( $attempt < $maxAttempts ) {
                    $this->sleep();
                    continue;
                }

                throw ApiException::transportFailure( $exception );
            }

            if ( $this->shouldRetryStatus( $response->status() ) && $attempt < $maxAttempts ) {
                $this->sleep();
                continue;
            }

            break;
        }

        if ( ! $response->successful() ) {
            throw ApiException::fromResponse( $response );
        }

        $body = $response->json();

        return is_array( $body ) ? $body : [];
    }

    /**
     * Perform a single HTTP request using a fresh access token.
     *
     * Extracted so the retry loop stays focused on control flow and so
     * subclasses may override request construction (e.g. adding extra
     * headers) without reimplementing the loop.
     *
     * @since 1.0.0
     *
     * @param  array<string, mixed>  $query
     * @param  array<string, mixed>|null  $json
     */
    protected function dispatch( string $method, string $url, array $query, ?array $json ): Response
    {
        $pending = $this->http
            ->withToken( $this->tokenProvider->accessToken() )
            ->timeout( $this->timeout )
            ->acceptJson();

        if ( [] !== $query ) {
            $pending = $pending->withOptions( [ 'query' => $query ] );
        }

        if ( null !== $json ) {
            return $pending->asJson()->send( $method, $url, [ 'json' => $json ] );
        }

        return $pending->send( $method, $url );
    }

    /**
     * Whether the given HTTP status code is one the client will retry.
     *
     * Only `429` (rate-limited) and the `5xx` server-error class are
     * retried; `4xx` client errors are surfaced immediately because
     * retrying them will not change the outcome.
     *
     * @since 1.0.0
     */
    protected function shouldRetryStatus( int $status ): bool
    {
        return 429 === $status || ( $status >= 500 && $status < 600 );
    }

    /**
     * Sleep between retry attempts.
     *
     * Extracted so tests can override with a zero-cost stub.
     *
     * @since 1.0.0
     */
    protected function sleep(): void
    {
        if ( $this->retrySleepMs > 0 ) {
            usleep( $this->retrySleepMs * 1000 );
        }
    }

    /**
     * Join the configured base URL with the requested path using a single
     * separating slash.
     *
     * @since 1.0.0
     */
    protected function buildUrl( string $path ): string
    {
        return rtrim( $this->baseUrl(), '/' ) . '/' . ltrim( $path, '/' );
    }
}
