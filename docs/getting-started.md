---
title: Getting Started
---

# Getting Started

This page walks through installing the package, binding a `TokenProvider`,
and making a first API call.

## Installation

```bash
composer require artisanpack-ui/google-business-profile
```

The service provider and the `GoogleBusinessProfile` facade alias are
auto-registered via Laravel's package discovery. There is no `migrate`
step — the package holds no state of its own.

## Prerequisites

Before the client can talk to the real Google Business Profile API you need:

1. A Google Cloud project with the four Business Profile APIs enabled
   (Account Management, Business Information, `mybusiness.googleapis.com`,
   and Business Profile Performance).
2. Approval on the [GBP API access request form](guide/access-approval.md).
   Development against `Http::fake()` does not require approval; production
   traffic does.
3. An OAuth access token issued for the `business.manage` scope. The
   package exposes this as `Scopes::BUSINESS_MANAGE`.

## Bind a `TokenProvider`

Every client accepts a `TokenProvider` and calls `accessToken()` on it
before each request. Bind whichever implementation fits your host:

```php
use ArtisanPackUI\GoogleBusinessProfile\Contracts\TokenProvider;

$this->app->bind( TokenProvider::class, function () {
    // In Keystone CMS, this resolves to GoogleOAuthManager. Anywhere
    // else, wire up whatever exposes a fresh access token.
    return new MyTokenProvider();
} );
```

See the [`TokenProvider` guide](guide/token-provider.md) for stub, cached,
and OAuth-backed examples.

## Make your first call

Resolve any of the four clients from the container. The `TokenProvider`
and the shared `Http` factory are wired in automatically:

```php
use ArtisanPackUI\GoogleBusinessProfile\AccountManagement\AccountManagementClient;

$client   = app( AccountManagementClient::class );
$accounts = $client->listAccounts();

foreach ( $accounts->accounts as $account ) {
    logger()->info( $account->name . ': ' . $account->accountName );
}
```

Every response is decoded into a typed DTO — `AccountList`, `Location`,
`Review`, and so on — under `src/{Family}/DataTransferObjects/`.

## Next steps

- Learn how [testing with `Http::fake()`](guide/testing.md) works and how
  the retry and error-mapping behaviour interacts with fakes.
- Read the [API-family map](reference/api-families.md) for the full list
  of methods exposed by each client.
