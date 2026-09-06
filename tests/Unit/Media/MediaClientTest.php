<?php

declare( strict_types=1 );

use ArtisanPackUI\GoogleBusinessProfile\Contracts\TokenProvider;
use ArtisanPackUI\GoogleBusinessProfile\Exceptions\ApiException;
use ArtisanPackUI\GoogleBusinessProfile\Media\DataTransferObjects\MediaItem;
use ArtisanPackUI\GoogleBusinessProfile\Media\DataTransferObjects\MediaItemDataRef;
use ArtisanPackUI\GoogleBusinessProfile\Media\MediaClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Request;

class StubMediaTokenProvider implements TokenProvider
{
    public function __construct( public string $token = 'stub-media-token' )
    {
    }

    public function accessToken(): string
    {
        return $this->token;
    }
}

function gbpMediaClient(
    HttpFactory $http,
    ?TokenProvider $provider = null,
    int $maxAttempts = 1,
): MediaClient {
    return new MediaClient(
        tokenProvider: $provider ?? new StubMediaTokenProvider(),
        http         : $http,
        timeout      : 5,
        maxAttempts  : $maxAttempts,
        retrySleepMs : 0,
    );
}

test( 'createMediaItem POSTs the payload against the location\'s /media sub-resource', function (): void {
    $factory = new HttpFactory();
    $factory->fake( [
        'mybusiness.googleapis.com/v4/accounts/1/locations/2/media' => $factory::response(
            [
                'name'                => 'accounts/1/locations/2/media/abc',
                'mediaFormat'         => 'PHOTO',
                'locationAssociation' => [ 'category' => 'COVER' ],
                'googleUrl'           => 'https://lh3.googleusercontent.com/abc',
                'createTime'          => '2026-08-14T18:00:00Z',
            ],
            200,
        ),
    ] );

    $client = gbpMediaClient( $factory, new StubMediaTokenProvider( 'the-token' ) );

    $payload = [
        'mediaFormat'         => 'PHOTO',
        'locationAssociation' => [ 'category' => 'COVER' ],
        'sourceUrl'           => 'https://cdn.example.test/front.jpg',
    ];

    $item = $client->createMediaItem( 'accounts/1/locations/2', $payload );

    expect( $item )->toBeInstanceOf( MediaItem::class );
    expect( $item->name )->toBe( 'accounts/1/locations/2/media/abc' );
    expect( $item->mediaFormat )->toBe( 'PHOTO' );
    expect( $item->googleUrl )->toBe( 'https://lh3.googleusercontent.com/abc' );

    $factory->assertSent( function ( Request $request ) use ( $payload ): bool {
        return 'POST' === $request->method()
            && 'https://mybusiness.googleapis.com/v4/accounts/1/locations/2/media' === $request->url()
            && 'Bearer the-token' === $request->header( 'Authorization' )[0]
            && str_contains( $request->header( 'Content-Type' )[0], 'application/json' )
            && $payload === $request->data();
    } );
} );

test( 'createMediaItem rejects an empty parent without dispatching', function (): void {
    $factory = new HttpFactory();
    $factory->fake( [ '*' => $factory::response( [], 200 ) ] );

    $client = gbpMediaClient( $factory );

    expect( fn (): MediaItem => $client->createMediaItem( '', [ 'mediaFormat' => 'PHOTO' ] ) )
        ->toThrow( InvalidArgumentException::class );

    $factory->assertSentCount( 0 );
} );

test( 'createMediaItem rejects an empty payload without dispatching', function (): void {
    $factory = new HttpFactory();
    $factory->fake( [ '*' => $factory::response( [], 200 ) ] );

    $client = gbpMediaClient( $factory );

    expect( fn (): MediaItem => $client->createMediaItem( 'accounts/1/locations/2', [] ) )
        ->toThrow( InvalidArgumentException::class );

    $factory->assertSentCount( 0 );
} );

test( 'createMediaItem maps API errors to ApiException carrying the status and body', function (): void {
    $factory = new HttpFactory();
    $factory->fake( [ '*' => $factory::response( 'invalid media', 400 ) ] );

    $client = gbpMediaClient( $factory );

    $thrown = null;

    try {
        $client->createMediaItem( 'accounts/1/locations/2', [ 'mediaFormat' => 'PHOTO' ] );
    } catch ( ApiException $exception ) {
        $thrown = $exception;
    }

    expect( $thrown )->not->toBeNull();
    expect( $thrown->statusCode() )->toBe( 400 );
    expect( $thrown->responseBody() )->toBe( 'invalid media' );
} );

test( 'startUpload POSTs to the media:startUpload verb and returns the data ref', function (): void {
    $factory = new HttpFactory();
    $factory->fake( [
        'mybusiness.googleapis.com/v4/accounts/1/locations/2/media:startUpload' => $factory::response(
            [ 'resourceName' => 'CAISABC123' ],
            200,
        ),
    ] );

    $client = gbpMediaClient( $factory, new StubMediaTokenProvider( 'the-token' ) );

    $dataRef = $client->startUpload( 'accounts/1/locations/2' );

    expect( $dataRef )->toBeInstanceOf( MediaItemDataRef::class );
    expect( $dataRef->resourceName )->toBe( 'CAISABC123' );

    $factory->assertSent( function ( Request $request ): bool {
        return 'POST' === $request->method()
            && 'https://mybusiness.googleapis.com/v4/accounts/1/locations/2/media:startUpload' === $request->url()
            && 'Bearer the-token' === $request->header( 'Authorization' )[0];
    } );
} );

test( 'startUpload rejects an empty parent without dispatching', function (): void {
    $factory = new HttpFactory();
    $factory->fake( [ '*' => $factory::response( [], 200 ) ] );

    $client = gbpMediaClient( $factory );

    expect( fn (): MediaItemDataRef => $client->startUpload( '' ) )
        ->toThrow( InvalidArgumentException::class );

    $factory->assertSentCount( 0 );
} );

test( 'startUpload maps API errors to ApiException carrying the status and body', function (): void {
    $factory = new HttpFactory();
    $factory->fake( [ '*' => $factory::response( 'forbidden', 403 ) ] );

    $client = gbpMediaClient( $factory );

    $thrown = null;

    try {
        $client->startUpload( 'accounts/1/locations/2' );
    } catch ( ApiException $exception ) {
        $thrown = $exception;
    }

    expect( $thrown )->not->toBeNull();
    expect( $thrown->statusCode() )->toBe( 403 );
} );

test( 'uploadMediaBytes POSTs the raw bytes to /upload/v1/media with upload_type=media', function (): void {
    $factory = new HttpFactory();
    $factory->fake( [
        'mybusiness.googleapis.com/upload/v1/media/*' => $factory::response( '', 200 ),
    ] );

    $client  = gbpMediaClient( $factory, new StubMediaTokenProvider( 'the-token' ) );
    $dataRef = new MediaItemDataRef( 'CAISABC123' );

    $client->uploadMediaBytes( $dataRef, 'raw-jpeg-bytes', 'image/jpeg' );

    $factory->assertSent( function ( Request $request ): bool {
        return 'POST' === $request->method()
            && str_starts_with( $request->url(), 'https://mybusiness.googleapis.com/upload/v1/media/CAISABC123' )
            && str_contains( $request->url(), 'upload_type=media' )
            && 'Bearer the-token' === $request->header( 'Authorization' )[0]
            && str_contains( $request->header( 'Content-Type' )[0], 'image/jpeg' )
            && 'raw-jpeg-bytes' === $request->body();
    } );
} );

test( 'uploadMediaBytes defaults the content type to application/octet-stream', function (): void {
    $factory = new HttpFactory();
    $factory->fake( [ '*' => $factory::response( '', 200 ) ] );

    $client = gbpMediaClient( $factory );

    $client->uploadMediaBytes( new MediaItemDataRef( 'CAISXYZ' ), 'bytes' );

    $factory->assertSent( function ( Request $request ): bool {
        return str_contains( $request->header( 'Content-Type' )[0], 'application/octet-stream' );
    } );
} );

test( 'uploadMediaBytes rejects an empty resource name without dispatching', function (): void {
    $factory = new HttpFactory();
    $factory->fake( [ '*' => $factory::response( '', 200 ) ] );

    $client = gbpMediaClient( $factory );

    expect( fn () => $client->uploadMediaBytes( new MediaItemDataRef( '' ), 'bytes' ) )
        ->toThrow( InvalidArgumentException::class );

    $factory->assertSentCount( 0 );
} );

test( 'uploadMediaBytes rejects empty contents without dispatching', function (): void {
    $factory = new HttpFactory();
    $factory->fake( [ '*' => $factory::response( '', 200 ) ] );

    $client = gbpMediaClient( $factory );

    expect( fn () => $client->uploadMediaBytes( new MediaItemDataRef( 'CAISXYZ' ), '' ) )
        ->toThrow( InvalidArgumentException::class );

    $factory->assertSentCount( 0 );
} );

test( 'uploadMediaBytes maps API errors to ApiException carrying the status and body', function (): void {
    $factory = new HttpFactory();
    $factory->fake( [ '*' => $factory::response( 'too large', 413 ) ] );

    $client = gbpMediaClient( $factory );

    $thrown = null;

    try {
        $client->uploadMediaBytes( new MediaItemDataRef( 'CAISXYZ' ), 'bytes' );
    } catch ( ApiException $exception ) {
        $thrown = $exception;
    }

    expect( $thrown )->not->toBeNull();
    expect( $thrown->statusCode() )->toBe( 413 );
    expect( $thrown->responseBody() )->toBe( 'too large' );
} );

test( 'uploadMediaBytes rejects a resourceName carrying query or fragment characters without dispatching', function (): void {
    $factory = new HttpFactory();
    $factory->fake( [ '*' => $factory::response( '', 200 ) ] );

    $client = gbpMediaClient( $factory );

    expect( fn () => $client->uploadMediaBytes( new MediaItemDataRef( 'CAIS?injected=1' ), 'bytes' ) )
        ->toThrow( InvalidArgumentException::class );

    expect( fn () => $client->uploadMediaBytes( new MediaItemDataRef( "CAIS\r\nX-Injected: 1" ), 'bytes' ) )
        ->toThrow( InvalidArgumentException::class );

    $factory->assertSentCount( 0 );
} );

test( 'uploadMediaBytes rejects a contentType carrying newline characters (header injection guard)', function (): void {
    $factory = new HttpFactory();
    $factory->fake( [ '*' => $factory::response( '', 200 ) ] );

    $client = gbpMediaClient( $factory );

    expect( fn () => $client->uploadMediaBytes(
        new MediaItemDataRef( 'CAISXYZ' ),
        'bytes',
        "image/jpeg\r\nX-Injected: 1",
    ) )->toThrow( InvalidArgumentException::class );

    $factory->assertSentCount( 0 );
} );

test( 'uploadMediaBytes retries a ConnectionException up to maxAttempts and returns on eventual success', function (): void {
    $factory  = new HttpFactory();
    $attempts = 0;
    $factory->fake( function () use ( &$attempts ) {
        $attempts++;

        if ( 1 === $attempts ) {
            throw new ConnectionException( 'connection reset' );
        }

        return HttpFactory::response( '', 200 );
    } );

    $client = gbpMediaClient( $factory, maxAttempts: 3 );

    $client->uploadMediaBytes( new MediaItemDataRef( 'CAISXYZ' ), 'bytes' );

    expect( $attempts )->toBe( 2 );
} );

test( 'uploadMediaBytes maps a ConnectionException on the last attempt to ApiException::transportFailure', function (): void {
    $factory = new HttpFactory();
    $factory->fake( function (): void {
        throw new ConnectionException( 'connection reset' );
    } );

    $client = gbpMediaClient( $factory, maxAttempts: 2 );

    $thrown = null;

    try {
        $client->uploadMediaBytes( new MediaItemDataRef( 'CAISXYZ' ), 'bytes' );
    } catch ( ApiException $exception ) {
        $thrown = $exception;
    }

    expect( $thrown )->not->toBeNull();
    expect( $thrown->getPrevious() )->toBeInstanceOf( ConnectionException::class );
} );

test( 'uploadMediaBytes retries retryable statuses up to maxAttempts and returns on eventual success', function (): void {
    $factory = new HttpFactory();
    $factory->fake( [
        '*' => $factory->sequence()
            ->push( '', 503 )
            ->push( '', 200 ),
    ] );

    $client = gbpMediaClient( $factory, maxAttempts: 3 );

    $client->uploadMediaBytes( new MediaItemDataRef( 'CAISXYZ' ), 'bytes' );

    $factory->assertSentCount( 2 );
} );

test( 'uploadMediaItem chains startUpload, uploadMediaBytes and createMediaItem in order', function (): void {
    $factory = new HttpFactory();
    $factory->fake( [
        'mybusiness.googleapis.com/v4/accounts/1/locations/2/media:startUpload' => $factory::response(
            [ 'resourceName' => 'CAISABC123' ],
            200,
        ),
        'mybusiness.googleapis.com/upload/v1/media/CAISABC123*'                 => $factory::response( '', 200 ),
        'mybusiness.googleapis.com/v4/accounts/1/locations/2/media'             => $factory::response(
            [
                'name'                => 'accounts/1/locations/2/media/created',
                'mediaFormat'         => 'PHOTO',
                'locationAssociation' => [ 'category' => 'COVER' ],
                'dataRef'             => [ 'resourceName' => 'CAISABC123' ],
            ],
            200,
        ),
    ] );

    $client = gbpMediaClient( $factory );

    $item = $client->uploadMediaItem(
        parent             : 'accounts/1/locations/2',
        contents           : 'raw-jpeg-bytes',
        mediaItemAttributes: [
            'mediaFormat'         => 'PHOTO',
            'locationAssociation' => [ 'category' => 'COVER' ],
        ],
        contentType        : 'image/jpeg',
    );

    expect( $item )->toBeInstanceOf( MediaItem::class );
    expect( $item->name )->toBe( 'accounts/1/locations/2/media/created' );
    expect( $item->dataRef )->not->toBeNull();
    expect( $item->dataRef->resourceName )->toBe( 'CAISABC123' );

    $factory->assertSentCount( 3 );

    $factory->assertSent( function ( Request $request ): bool {
        return str_ends_with( $request->url(), '/media:startUpload' );
    } );

    $factory->assertSent( function ( Request $request ): bool {
        return str_starts_with( $request->url(), 'https://mybusiness.googleapis.com/upload/v1/media/CAISABC123' )
            && 'raw-jpeg-bytes' === $request->body();
    } );

    $factory->assertSent( function ( Request $request ): bool {
        return 'https://mybusiness.googleapis.com/v4/accounts/1/locations/2/media' === $request->url()
            && [
                'mediaFormat'         => 'PHOTO',
                'locationAssociation' => [ 'category' => 'COVER' ],
                'dataRef'             => [ 'resourceName' => 'CAISABC123' ],
            ] === $request->data();
    } );
} );

test( 'uploadMediaItem overwrites any caller-supplied dataRef with the fresh upload identifier', function (): void {
    $factory = new HttpFactory();
    $factory->fake( [
        '*media:startUpload' => $factory::response( [ 'resourceName' => 'CAISFRESH' ], 200 ),
        '*upload/v1/media/*' => $factory::response( '', 200 ),
        '*/media'            => $factory::response( [ 'name' => 'accounts/1/locations/2/media/x' ], 200 ),
    ] );

    $client = gbpMediaClient( $factory );

    $client->uploadMediaItem(
        parent             : 'accounts/1/locations/2',
        contents           : 'bytes',
        mediaItemAttributes: [
            'mediaFormat' => 'PHOTO',
            'dataRef'     => [ 'resourceName' => 'CAISSTALE' ],
        ],
    );

    $factory->assertSent( function ( Request $request ): bool {
        if ( 'https://mybusiness.googleapis.com/v4/accounts/1/locations/2/media' !== $request->url() ) {
            return false;
        }

        $data = $request->data();

        return isset( $data['dataRef']['resourceName'] )
            && 'CAISFRESH' === $data['dataRef']['resourceName'];
    } );
} );

test( 'uploadMediaItem aborts before uploading bytes when startUpload fails', function (): void {
    $factory = new HttpFactory();
    $factory->fake( [
        '*media:startUpload' => $factory::response( 'forbidden', 403 ),
        '*upload/v1/media/*' => $factory::response( '', 200 ),
        '*/media'            => $factory::response( [ 'name' => 'x' ], 200 ),
    ] );

    $client = gbpMediaClient( $factory );

    expect( fn (): MediaItem => $client->uploadMediaItem(
        'accounts/1/locations/2',
        'bytes',
        [ 'mediaFormat' => 'PHOTO' ],
    ) )->toThrow( ApiException::class );

    // Only the startUpload call went out — no byte upload, no createMediaItem.
    $factory->assertSentCount( 1 );
} );

test( 'uploadMediaItem rejects an empty parent without dispatching', function (): void {
    $factory = new HttpFactory();
    $factory->fake( [ '*' => $factory::response( [], 200 ) ] );

    $client = gbpMediaClient( $factory );

    expect( fn (): MediaItem => $client->uploadMediaItem( '', 'bytes', [ 'mediaFormat' => 'PHOTO' ] ) )
        ->toThrow( InvalidArgumentException::class );

    $factory->assertSentCount( 0 );
} );

test( 'uploadMediaItem rejects empty contents without dispatching', function (): void {
    $factory = new HttpFactory();
    $factory->fake( [ '*' => $factory::response( [], 200 ) ] );

    $client = gbpMediaClient( $factory );

    expect( fn (): MediaItem => $client->uploadMediaItem( 'accounts/1/locations/2', '', [ 'mediaFormat' => 'PHOTO' ] ) )
        ->toThrow( InvalidArgumentException::class );

    $factory->assertSentCount( 0 );
} );
