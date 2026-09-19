# ArtisanPack UI Google Business Profile

OAuth-free, UI-free API client for the Google Business Profile family of APIs
(accounts, locations, performance, reviews, posts, and media).

This package is a pure HTTP client built on Laravel's `Http` facade. It brings
no OAuth flow and no UI of its own. Callers pass in any `TokenProvider` — the
Keystone CMS plugin, for example, binds it to `GoogleOAuthManager` from
`artisanpack-ui/google`.

> **Google Business Profile API access is approval-gated per Google Cloud
> project.** That gates production release, not local development against
> `Http::fake()`. See the [access-approval note](docs/guide/access-approval.md)
> for details.

## Installation

```bash
composer require artisanpack-ui/google-business-profile
```

The package auto-registers its service provider and the
`GoogleBusinessProfile` facade alias via Laravel's package discovery.

## The `TokenProvider` contract

Every resource client accepts a `TokenProvider` and calls `accessToken()`
on it before each request. The client itself performs no OAuth — it
delegates token acquisition (including refresh) to whichever provider the
host application binds.

```php
namespace ArtisanPackUI\GoogleBusinessProfile\Contracts;

interface TokenProvider
{
    public function accessToken(): string;
}
```

In Keystone CMS the provider is bound to `GoogleOAuthManager`, which
transparently refreshes expired tokens. In tests it is typically a stub
that returns a fixed string. Anywhere else, bind whatever fits.

See the [`TokenProvider` guide](docs/guide/token-provider.md) for
worked examples of each binding style.

## The four API families

The Google Business Profile surface is spread across four separate host
names. This package wraps each family with a dedicated client that shares
the same retry and error-mapping foundation:

| Family                | Client                        | Base URL |
|-----------------------|-------------------------------|----------|
| Account Management    | `AccountManagementClient`     | `https://mybusinessaccountmanagement.googleapis.com/v1` |
| Business Information  | `BusinessInformationClient`   | `https://mybusinessbusinessinformation.googleapis.com/v1` |
| Legacy v4 (Reviews, Local Posts, Media) | `ReviewsClient`, `LocalPostsClient`, `MediaClient` | `https://mybusiness.googleapis.com/v4` |
| Performance           | `PerformanceClient`           | `https://businessprofileperformance.googleapis.com/v1` |

The full method-by-method map lives in
[docs/reference/api-families.md](docs/reference/api-families.md).

## Testing with `Http::fake()`

Every client resolves the same `Illuminate\Http\Client\Factory` singleton
that the `Http` facade resolves, so `Http::fake()` works out of the box —
no bespoke test doubles required.

```php
use Illuminate\Support\Facades\Http;

Http::fake( [
    'mybusinessaccountmanagement.googleapis.com/*' => Http::response( [
        'accounts' => [
            [ 'name' => 'accounts/123', 'accountName' => 'Test Business' ],
        ],
    ] ),
] );

$accounts = app( AccountManagementClient::class )->listAccounts();
```

See the [testing guide](docs/guide/testing.md) for retry behaviour, error
mapping, and a full walk-through of faking each API family.

## Documentation

The full documentation set lives in [`docs/`](docs/home.md):

- [Getting started](docs/getting-started.md)
- [Guide](docs/guide.md) — token providers, testing, and access approval
- [Reference](docs/reference.md) — the API-family map and per-client method list

## Contributing

As an open source project, this package is open to contributions from anyone.
Please [read through the contributing guidelines](CONTRIBUTING.md) to learn
more about how you can contribute to this project.
