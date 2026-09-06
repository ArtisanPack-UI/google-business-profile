# ArtisanPack UI Google Business Profile Changelog

## Unreleased

### Changed

- `ApiException::fromResponse()` now distinguishes the Google Business
  Profile "access not approved" flavor of `HTTP 429` from ordinary
  per-minute rate limiting. When Google's `google.rpc.ErrorInfo` detail
  reports `reason: RATE_LIMIT_EXCEEDED` with `quota_limit_value: "0"`
  — the signature Google returns until the Cloud project is approved
  for Business Profile API access — the exception message points at the
  access request form instead of the generic "rate-limited" text. The
  parser is defensive: malformed bodies, missing keys, or non-scalar
  types fall back to the original message without throwing.

### Added

- `GoogleBusinessProfileServiceProvider::boot()` now auto-registers the
  `Scopes::BUSINESS_MANAGE` OAuth scope with the `artisanpack-ui/google`
  package's `ScopeRegistry` when that package is present and its
  service provider is registered. Downstream hosts no longer have to
  add the scope in their own boot code; hosts that bind their own
  `TokenProvider` without `artisanpack-ui/google`, and hosts that
  `dont-discover` the Google service provider, are unaffected (two
  guards — `class_exists( Google::class )` and
  `$this->app->bound( 'google' )` — keep the boot a no-op).
- v4 legacy Reviews API client (`ReviewsClient::listReviews()` and
  `ReviewsClient::replyToReview()`) with typed `Review`, `ReviewList`,
  `Reviewer`, and `ReviewReply` DTOs. `listReviews()` accepts `pageSize`,
  `pageToken`, and `orderBy` query parameters; rejects an empty parent
  and any `pageSize` outside Google's documented `1..50` range with an
  `InvalidArgumentException` before dispatching; surfaces the
  location-wide `averageRating` and `totalReviewCount` aggregates on
  every page. `replyToReview()` upserts the merchant reply via `PUT` on
  the review's `/reply` sub-resource, trims the comment, and rejects an
  empty review name, whitespace-only body, or a body that exceeds
  Google's documented 4096-byte cap
  (`ReviewsClient::MAX_REPLY_COMMENT_BYTES`) before dispatching. The
  `Review` DTO preserves the raw payload for forward compatibility and
  maps the string `starRating` enum to `1..5` via `numericRating()`. The
  `ReviewReply` DTO exposes Google's moderation surface
  (`reviewReplyState`, `policyViolation`).
- Account Management API client (`AccountManagementClient::listAccounts()`)
  with typed `Account` and `AccountList` DTOs. Accepts `pageSize`,
  `pageToken`, `filter`, and `parentAccount` query parameters; rejects
  a `pageSize` outside Google's documented `1..20` range with an
  `InvalidArgumentException` before dispatching; preserves the raw
  account payload on the DTO for forward compatibility.
