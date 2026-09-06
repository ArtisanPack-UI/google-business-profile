# ArtisanPack UI Google Business Profile

OAuth-free, UI-free API client for the Google Business Profile family of APIs
(locations, performance, reviews, posts).

This package is a pure HTTP client built on Laravel's `Http` facade. It brings
no OAuth flow and no UI of its own. Callers pass in any `TokenProvider` — the
Keystone CMS plugin, for example, binds it to `GoogleOAuthManager` from
`artisanpack-ui/google`.

> Google Business Profile API access is approval-gated per Google Cloud
> project. That gates production release, not local development against
> `Http::fake()`.

## Installation

```bash
composer require artisanpack-ui/google-business-profile
```

## Usage

Feature code lands in subsequent issues. This release is the package skeleton
only.

## Contributing

As an open source project, this package is open to contributions from anyone.
Please [read through the contributing guidelines](CONTRIBUTING.md) to learn
more about how you can contribute to this project.
