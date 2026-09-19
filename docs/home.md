---
title: ArtisanPack UI Google Business Profile
---

# ArtisanPack UI Google Business Profile

OAuth-free, UI-free API client for the Google Business Profile family of
APIs. Sits alongside `artisanpack-ui/google` (which supplies the OAuth
manager) as a shared HTTP layer that other ArtisanPack UI packages and
downstream host applications can consume.

## What's in this package

- A **`TokenProvider` contract** — a one-method interface the client uses
  to obtain a fresh access token on every request. No OAuth lives here;
  the host binds whatever provider fits.
- **Four API families, six resource clients** — Account Management,
  Business Information, Performance, and the Legacy v4 host (which
  serves `ReviewsClient`, `LocalPostsClient`, and `MediaClient`) — all
  built on a shared `BaseClient` that handles auth, retries, and error
  mapping.
- **First-class `Http::fake()` support** — every client resolves the same
  factory the `Http` facade resolves, so tests can stub responses without
  any bespoke test doubles.
- A single **`Scopes` constant** for the one OAuth scope the entire
  Business Profile surface requires, so the OAuth manager and the API
  client agree on what to request.

## Documentation

- [Getting started](getting-started.md) — install the package, bind a
  `TokenProvider`, and make your first call.
- [Guide](guide.md) — worked examples for the token contract, testing
  with `Http::fake()`, and the GBP API access-approval prerequisite.
- [Reference](reference.md) — the four API families this package wraps,
  base URLs, and the methods exposed by each client.
