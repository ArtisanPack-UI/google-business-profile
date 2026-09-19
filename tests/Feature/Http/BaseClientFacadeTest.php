<?php

declare( strict_types=1 );

use ArtisanPackUI\GoogleBusinessProfile\Contracts\TokenProvider;
use ArtisanPackUI\GoogleBusinessProfile\Http\BaseClient;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

class FacadeGbpClient extends BaseClient
{
    public function call( string $method, string $path ): array
    {
        return $this->request( $method, $path );
    }

    protected function baseUrl(): string
    {
        return 'https://mybusinessbusinessinformation.googleapis.com/v1';
    }
}

class FacadeStubTokenProvider implements TokenProvider
{
    public function accessToken(): string
    {
        return 'stub-access-token';
    }
}

test( 'BaseClient uses the shared HttpFactory so Http::fake() intercepts its requests', function (): void {
    Http::fake( [
        'mybusinessbusinessinformation.googleapis.com/*' => Http::response(
            [ 'via' => 'facade' ],
            200,
        ),
    ] );

    $client = new FacadeGbpClient(
        tokenProvider: new FacadeStubTokenProvider(),
        http: app( HttpFactory::class ),
        timeout: 5,
        maxAttempts: 1,
        retrySleepMs: 0,
    );

    expect( $client->call( 'GET', 'accounts' ) )->toBe( [ 'via' => 'facade' ] );

    Http::assertSent( function ( Request $request ): bool {
        return 'https://mybusinessbusinessinformation.googleapis.com/v1/accounts' === $request->url()
            && 'Bearer stub-access-token' === $request->header( 'Authorization' )[0];
    } );
} );
