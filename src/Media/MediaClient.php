<?php

/**
 * Google Business Profile v4 legacy Media API client.
 *
 * @package    ArtisanPack_UI
 * @subpackage GoogleBusinessProfile
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\GoogleBusinessProfile\Media;

use ArtisanPackUI\GoogleBusinessProfile\Exceptions\ApiException;
use ArtisanPackUI\GoogleBusinessProfile\Http\BaseClient;
use ArtisanPackUI\GoogleBusinessProfile\Media\DataTransferObjects\MediaItem;
use ArtisanPackUI\GoogleBusinessProfile\Media\DataTransferObjects\MediaItemDataRef;
use Illuminate\Http\Client\ConnectionException;
use InvalidArgumentException;

/**
 * Typed client for the Google Business Profile v4 legacy Media API.
 *
 * Media items (photos and videos attached to a location's listing) are one
 * of the surfaces Google has not migrated off the v4
 * `mybusiness.googleapis.com` host, so this client is scoped to that legacy
 * base URL. Callers should not confuse it with the v1 Business Information,
 * Account Management, or Performance clients, which each live on their own
 * dedicated hosts.
 *
 * Exposes the endpoints needed to upload a photo to a location's listing:
 *
 * - `accounts.locations.media.startUpload` — reserve an upload slot and
 *   receive a {@see MediaItemDataRef} pointing at it.
 * - Byte upload — POST the raw file bytes to
 *   `https://mybusiness.googleapis.com/upload/v1/media/{resourceName}` so
 *   Google can associate them with the data ref.
 * - `accounts.locations.media.create` — create the {@see MediaItem} record,
 *   either referencing the uploaded bytes via a `dataRef` or pointing at a
 *   publicly-fetchable `sourceUrl`.
 *
 * The three steps are surfaced individually so callers driving a resumable
 * upload UI can interleave progress reporting, and are also wrapped by the
 * {@see self::uploadMediaItem()} convenience that runs them in sequence.
 *
 * Every request rides the shared {@see BaseClient} plumbing — a Bearer token
 * fetched from the injected `TokenProvider`, retry on `429`/`5xx`, and
 * mapping of errors to
 * {@see ApiException} — with
 * a specialised byte-upload path that shares the token, timeout, and retry
 * settings but targets Google's `/upload/v1/media` sub-host with a raw
 * request body instead of JSON.
 *
 * @package    ArtisanPack_UI
 * @subpackage GoogleBusinessProfile
 *
 * @since      1.0.0
 */
class MediaClient extends BaseClient
{
    /**
     * Absolute URL for the byte-upload sub-host that Google's v4 media
     * upload flow expects. The `{resourceName}` returned by
     * `media:startUpload` is appended verbatim (it already contains any
     * required slashes) and `?upload_type=media` is added by
     * {@see self::uploadMediaBytes()}.
     *
     * @since 1.0.0
     *
     * @var string
     */
    protected const UPLOAD_BASE_URL = 'https://mybusiness.googleapis.com/upload/v1/media';

    /**
     * Create a media item on a location's listing.
     *
     * The `$mediaItem` payload is sent verbatim as the JSON body and must be
     * a non-empty associative array shaped like a v4 `MediaItem` resource.
     * Callers must populate either `sourceUrl` (to have Google fetch the
     * media from a public URL) or `dataRef.resourceName` (returned from a
     * prior {@see self::startUpload()} + {@see self::uploadMediaBytes()}
     * pair), plus `mediaFormat` and a `locationAssociation` describing where
     * on the listing the item should appear. Google enforces the exact
     * requirements server-side; a rejected payload surfaces as an
     * {@see ApiException}.
     *
     * @since 1.0.0
     *
     * @param  string  $parent  Parent location resource name
     *                          (`accounts/{accountId}/locations/{locationId}`).
     * @param  array<string, mixed>  $mediaItem  Media item payload shaped
     *                                           like a v4 `MediaItem`
     *                                           resource.
     *
     * @throws InvalidArgumentException When `parent` is empty or `mediaItem`
     *                                  is an empty array.
     * @throws ApiException When the API returns an error or the request
     *                     fails at the transport level after all retries.
     */
    public function createMediaItem( string $parent, array $mediaItem ): MediaItem
    {
        if ( '' === $parent ) {
            throw new InvalidArgumentException( 'parent is required and must be a non-empty location resource name.' );
        }

        if ( [] === $mediaItem ) {
            throw new InvalidArgumentException( 'mediaItem is required and must be a non-empty media item payload.' );
        }

        $body = $this->request( 'POST', $parent . '/media', [], $mediaItem );

        return MediaItem::fromArray( $body );
    }

    /**
     * Start a resumable media upload against a location.
     *
     * Returns the {@see MediaItemDataRef} that identifies the reserved upload
     * slot. The returned resource name must be forwarded to
     * {@see self::uploadMediaBytes()} to transfer the media bytes, and then
     * to {@see self::createMediaItem()} inside the `dataRef.resourceName`
     * field so Google can associate the two.
     *
     * @since 1.0.0
     *
     * @param  string  $parent  Parent location resource name
     *                          (`accounts/{accountId}/locations/{locationId}`).
     *
     * @throws InvalidArgumentException When `parent` is empty.
     * @throws ApiException When the API returns an error or the request
     *                     fails at the transport level after all retries.
     */
    public function startUpload( string $parent ): MediaItemDataRef
    {
        if ( '' === $parent ) {
            throw new InvalidArgumentException( 'parent is required and must be a non-empty location resource name.' );
        }

        // `media:startUpload` takes no required fields; we intentionally send
        // an empty body rather than `{}`. Laravel's `['json' => []]` would
        // serialise to the JSON array literal `[]`, which some Google
        // endpoints reject as an invalid message.
        $body = $this->request( 'POST', $parent . '/media:startUpload' );

        return MediaItemDataRef::fromArray( $body );
    }

    /**
     * Upload the raw media bytes referenced by a data ref.
     *
     * POSTs `$contents` to Google's `/upload/v1/media/{resourceName}`
     * endpoint with `?upload_type=media`, using the same Bearer token,
     * timeout, and retry policy as the JSON request path. The endpoint
     * returns an empty body on success; this method therefore returns void
     * and raises {@see ApiException} on any non-2xx response.
     *
     * Content type defaults to `application/octet-stream`, which is what
     * Google's example client sends when the caller does not know the exact
     * media type; supply a specific type (e.g. `image/jpeg`) when it is
     * known to give Google a stronger validation signal.
     *
     * @since 1.0.0
     *
     * @param  MediaItemDataRef  $dataRef  Data ref returned by
     *                                     {@see self::startUpload()}.
     * @param  string  $contents  Raw media bytes to upload.
     * @param  string  $contentType  MIME type of `$contents`.
     *
     * @throws InvalidArgumentException When `dataRef` carries an empty
     *                                  resource name or `contents` is
     *                                  empty.
     * @throws ApiException When the API returns an error or the request
     *                     fails at the transport level after all retries.
     */
    public function uploadMediaBytes(
        MediaItemDataRef $dataRef,
        string $contents,
        string $contentType = 'application/octet-stream',
    ): void {
        if ( '' === $dataRef->resourceName ) {
            throw new InvalidArgumentException( 'dataRef.resourceName is required and must be a non-empty upload identifier.' );
        }

        // Guard against a caller-supplied `resourceName` that would silently
        // corrupt the upload URL. Google-issued resource names contain only
        // path-safe characters; a `?` or `#` would confuse the query-string
        // merge with `upload_type=media` (or, worse, let a caller inject one).
        if ( false !== strpbrk( $dataRef->resourceName, "?#\r\n" ) ) {
            throw new InvalidArgumentException( 'dataRef.resourceName must not contain query, fragment, or newline characters.' );
        }

        if ( '' === $contents ) {
            throw new InvalidArgumentException( 'contents is required and must be a non-empty byte string.' );
        }

        // Reject header-injection attempts up front. `withBody()` passes the
        // Content-Type value verbatim to Guzzle, which does not validate it.
        if ( false !== strpbrk( $contentType, "\r\n" ) ) {
            throw new InvalidArgumentException( 'contentType must not contain newline characters.' );
        }

        $url         = self::UPLOAD_BASE_URL . '/' . ltrim( $dataRef->resourceName, '/' );
        $maxAttempts = max( 1, $this->maxAttempts );
        $response    = null;

        for ( $attempt = 1; $attempt <= $maxAttempts; $attempt++ ) {
            try {
                $response = $this->http
                    ->withToken( $this->tokenProvider->accessToken() )
                    ->timeout( $this->timeout )
                    ->withBody( $contents, $contentType )
                    ->withOptions( [ 'query' => [ 'upload_type' => 'media' ] ] )
                    ->post( $url );
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
    }

    /**
     * Convenience: run the full resumable upload flow end-to-end.
     *
     * Chains {@see self::startUpload()}, {@see self::uploadMediaBytes()},
     * and {@see self::createMediaItem()} so callers with the media bytes
     * already in hand can create a media item in one call. Any
     * `dataRef.resourceName` value the caller passed inside
     * `$mediaItemAttributes` is overwritten with the resource name returned
     * by `startUpload`; a caller-supplied `sourceUrl` is left in place but
     * Google rejects payloads that carry both, so callers should not mix
     * the two flows.
     *
     * @since 1.0.0
     *
     * @param  string  $parent  Parent location resource name
     *                          (`accounts/{accountId}/locations/{locationId}`).
     * @param  string  $contents  Raw media bytes to upload.
     * @param  array<string, mixed>  $mediaItemAttributes  Additional
     *                                                     attributes for
     *                                                     the media item
     *                                                     (`mediaFormat`,
     *                                                     `locationAssociation`,
     *                                                     `description`,
     *                                                     etc.). The
     *                                                     `dataRef` field is
     *                                                     set by this
     *                                                     method.
     * @param  string  $contentType  MIME type of `$contents`.
     *
     * @throws InvalidArgumentException When `parent` or `contents` is empty,
     *                                  or when `startUpload` succeeds but
     *                                  returns an empty `resourceName` (a
     *                                  malformed API response that the
     *                                  chained {@see self::uploadMediaBytes()}
     *                                  call refuses).
     * @throws ApiException When any step fails after all retries.
     */
    public function uploadMediaItem(
        string $parent,
        string $contents,
        array $mediaItemAttributes = [],
        string $contentType = 'application/octet-stream',
    ): MediaItem {
        if ( '' === $parent ) {
            throw new InvalidArgumentException( 'parent is required and must be a non-empty location resource name.' );
        }

        if ( '' === $contents ) {
            throw new InvalidArgumentException( 'contents is required and must be a non-empty byte string.' );
        }

        $dataRef = $this->startUpload( $parent );

        $this->uploadMediaBytes( $dataRef, $contents, $contentType );

        $mediaItemAttributes['dataRef'] = [ 'resourceName' => $dataRef->resourceName ];

        return $this->createMediaItem( $parent, $mediaItemAttributes );
    }

    /**
     * Base URL for the Google Business Profile v4 legacy surface.
     *
     * @since 1.0.0
     */
    protected function baseUrl(): string
    {
        return 'https://mybusiness.googleapis.com/v4';
    }
}
