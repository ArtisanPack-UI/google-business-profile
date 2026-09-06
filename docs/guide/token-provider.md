---
title: Token Provider Contract
---

# Token Provider Contract

Every resource client in this package accepts a `TokenProvider` in its
constructor and calls `accessToken()` on it before each outgoing request.
The client itself performs no OAuth — it delegates token acquisition
(and refresh) to whichever provider the host application binds.

## The interface

```php
namespace ArtisanPackUI\GoogleBusinessProfile\Contracts;

interface TokenProvider
{
    /**
     * Return a valid Google OAuth access token to use as the Bearer
     * credential on Google Business Profile API requests.
     *
     * Implementations are responsible for refreshing expired tokens
     * before returning; the client will use the returned string verbatim.
     */
    public function accessToken(): string;
}
```

Two properties matter:

1. **It is called on every request.** There is no client-side cache. If
   the provider is expensive to invoke (for example, it hits a remote
   OAuth server), cache inside the provider.
2. **The returned token must already be fresh.** The client attaches it
   verbatim as `Authorization: Bearer <token>` and does not attempt to
   refresh on `401`.

## Required scope

Every method on every client uses a single OAuth scope:

```php
use ArtisanPackUI\GoogleBusinessProfile\Scopes\Scopes;

Scopes::BUSINESS_MANAGE; // https://www.googleapis.com/auth/business.manage
```

Whichever OAuth manager mints the token — the Keystone `GoogleOAuthManager`,
Laravel Socialite, or your own — must request `business.manage`.

## Binding patterns

### Stub (tests, local dev)

```php
use ArtisanPackUI\GoogleBusinessProfile\Contracts\TokenProvider;

$this->app->bind( TokenProvider::class, function () {
    return new class implements TokenProvider {
        public function accessToken(): string
        {
            return 'test-token';
        }
    };
} );
```

Paired with `Http::fake()`, this is enough to exercise every client
without ever touching Google.

### Cached provider

```php
use ArtisanPackUI\GoogleBusinessProfile\Contracts\TokenProvider;
use Illuminate\Support\Facades\Cache;

class CachedTokenProvider implements TokenProvider
{
    public function __construct( private string $cacheKey ) {}

    public function accessToken(): string
    {
        return Cache::remember( $this->cacheKey, now()->addMinutes( 55 ), function () {
            return $this->mintFreshToken();
        } );
    }

    private function mintFreshToken(): string
    {
        // …
    }
}
```

Google's access tokens are valid for 60 minutes; caching for 55 leaves a
safe refresh window.

### Keystone `GoogleOAuthManager`

Inside Keystone CMS the plugin binds `TokenProvider` to a thin adapter
around `GoogleOAuthManager` from `artisanpack-ui/google`. `GoogleOAuthManager`
already handles refresh, storage, and encryption, so the adapter is a
one-liner that delegates to whichever access-token accessor the manager
exposes. The binding — and the adapter — live in the Keystone plugin's
service provider, not in this package.

## Failure handling

If the provider cannot supply a token, throw. The client makes no attempt
to recover — an unauthenticated request will be rejected by Google with a
`401`, and the client will surface that as an `ApiException`. Surfacing
the failure from the provider instead lets the caller distinguish
"we never had a token" from "Google rejected our token".

See also: [[testing]] for stubbing the provider under `Http::fake()`.
