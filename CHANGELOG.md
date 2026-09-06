# ArtisanPack UI Google Business Profile Changelog

## Unreleased

### Added

- v4 legacy Reviews API client (`ReviewsClient::listReviews()` and
  `ReviewsClient::replyToReview()`) with typed `Review`, `ReviewList`,
  `Reviewer`, and `ReviewReply` DTOs. `listReviews()` accepts `pageSize`,
  `pageToken`, and `orderBy` query parameters; rejects an empty parent
  and any `pageSize` outside Google's documented `1..50` range with an
  `InvalidArgumentException` before dispatching; surfaces the
  location-wide `averageRating` and `totalReviewCount` aggregates on
  every page. `replyToReview()` upserts the merchant reply via `PUT` on
  the review's `/reply` sub-resource, trims the comment, and rejects an
  empty review name or whitespace-only body before dispatching. The
  `Review` DTO preserves the raw payload for forward compatibility and
  maps the string `starRating` enum to `1..5` via `numericRating()`.
- Account Management API client (`AccountManagementClient::listAccounts()`)
  with typed `Account` and `AccountList` DTOs. Accepts `pageSize`,
  `pageToken`, `filter`, and `parentAccount` query parameters; rejects
  a `pageSize` outside Google's documented `1..20` range with an
  `InvalidArgumentException` before dispatching; preserves the raw
  account payload on the DTO for forward compatibility.
