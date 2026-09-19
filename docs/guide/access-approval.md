---
title: GBP API Access Approval
---

# GBP API Access Approval

Every Google Cloud project that wants to call the Google Business Profile
API family in production must be approved by Google. This is a manual
review that Google runs separately from OAuth consent screen verification.
It **gates production release, not local development**.

## What is gated

All four API families this package wraps require access approval before
they will respond with real data:

- `mybusinessaccountmanagement.googleapis.com`
- `mybusinessbusinessinformation.googleapis.com`
- `mybusiness.googleapis.com` (legacy v4: Reviews, Local Posts, Media)
- `businessprofileperformance.googleapis.com`

Without approval, requests return `403` with a message about the project
not being allowlisted. The client surfaces this as an `ApiException`.

## What is not gated

- **Development against `Http::fake()`.** The fake factory never leaves
  the process, so approval is irrelevant. The whole test suite for this
  package runs unapproved.
- **Enabling the four APIs in the Google Cloud console.** You can enable
  them today; approval is a separate step that decides whether real
  requests succeed.
- **Minting OAuth access tokens for the `business.manage` scope.**
  Tokens will mint fine before approval; the `403` comes back from the
  API surface itself.

## Requesting approval

1. Enable the four Business Profile APIs in the Google Cloud console for
   your project.
2. Submit the [Business Profile APIs access request form][form]. Google
   asks for the intended use case, a screenshot or description of the
   integration, and the Google Cloud project number.
3. Wait. Turnaround is typically a few business days but can stretch to
   weeks. Google may follow up asking for clarification — respond
   promptly to avoid resetting the queue.

Approval is per Google Cloud project. If you rotate to a new project
(for example, moving from a personal sandbox to a company-owned
project), the new project has to be approved separately.

[form]: https://support.google.com/business/contact/api_default

## Keeping development unblocked

While approval is pending:

- Write and test all client code against `Http::fake()` — see the
  [testing guide](testing.md).
- Wire up the [`TokenProvider`](token-provider.md) binding against a
  stub. No real token is needed.
- Capture representative response payloads from Google's public
  documentation and inline them into the fakes as fixtures.

Once approval lands, swap the stub `TokenProvider` for the real OAuth-backed
one (in Keystone, `GoogleOAuthManager`) and remove the `Http::fake()`
call. No client code has to change.

See also: [Token provider contract](token-provider.md) and
[Testing with `Http::fake()`](testing.md).
