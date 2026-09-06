---
title: Guide
---

# Guide

Practical guides for wiring up the Google Business Profile package inside
a host application or downstream ArtisanPack UI package.

## Topics

- [Token provider contract](guide/token-provider.md) — the one-method
  interface every client depends on, plus stub, cached, and OAuth-backed
  binding patterns (including the Keystone `GoogleOAuthManager` binding).
- [Testing with `Http::fake()`](guide/testing.md) — how the shared `Http`
  factory makes fakes trivial, how retries interact with fake sequences,
  and how `ApiException` maps to non-2xx responses and transport failures.
- [Access approval](guide/access-approval.md) — the GBP API access request
  process, what it gates (release, not development), and how to keep
  local work unblocked while approval is pending.

See also: [API families](reference/api-families.md) for the reference
map of every method each client exposes.
