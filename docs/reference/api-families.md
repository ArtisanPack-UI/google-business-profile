---
title: API Families
---

# API Families

The Google Business Profile surface is split across four host names.
This package wraps each with a dedicated client that inherits from
`BaseClient` (auth, retries, JSON encoding, error mapping). All clients
share one OAuth scope: `Scopes::BUSINESS_MANAGE`.

## 1. Account Management

- **Host**: `https://mybusinessaccountmanagement.googleapis.com/v1`
- **Client**: `ArtisanPackUI\GoogleBusinessProfile\AccountManagement\AccountManagementClient`
- **Google docs**: [My Business Account Management API][gbp-am]

| Method            | Returns       | Purpose |
|-------------------|---------------|---------|
| `listAccounts()`  | `AccountList` | List every account the authenticated user administers or owns. |

[gbp-am]: https://developers.google.com/my-business/reference/accountmanagement/rest

## 2. Business Information

- **Host**: `https://mybusinessbusinessinformation.googleapis.com/v1`
- **Client**: `ArtisanPackUI\GoogleBusinessProfile\BusinessInformation\BusinessInformationClient`
- **Google docs**: [My Business Business Information API][gbp-bi]

| Method                                       | Returns        | Purpose |
|----------------------------------------------|----------------|---------|
| `listLocations( string $parent, … )`         | `LocationList` | Paginate locations belonging to an account. |
| `getLocation( string $name, $readMask )`     | `Location`     | Fetch one location, constrained by a `readMask`. |
| `patchLocation( string $name, array $body, $updateMask )` | `Location` | Update a location; only fields in the `updateMask` are written. |

[gbp-bi]: https://developers.google.com/my-business/reference/businessinformation/rest

## 3. Legacy v4 — Reviews, Local Posts, Media

- **Host**: `https://mybusiness.googleapis.com/v4`
- **Google docs**: [My Business API v4][gbp-v4]

Google keeps three resource families on the legacy v4 host. Each has its
own client in this package:

### Reviews

- **Client**: `ArtisanPackUI\GoogleBusinessProfile\Reviews\ReviewsClient`

| Method                                             | Returns       | Purpose |
|----------------------------------------------------|---------------|---------|
| `listReviews( string $parent, … )`                 | `ReviewList`  | Paginate reviews for a location. |
| `replyToReview( string $name, string $comment )`   | `ReviewReply` | Post or update the business's reply to a review. |

### Local Posts

- **Client**: `ArtisanPackUI\GoogleBusinessProfile\LocalPosts\LocalPostsClient`

| Method                                             | Returns          | Purpose |
|----------------------------------------------------|------------------|---------|
| `createLocalPost( string $parent, array $post )`   | `LocalPost`      | Publish a local post (What's New, Event, Offer, or Product) on a location. |
| `listLocalPosts( string $parent, … )`              | `LocalPostList`  | Paginate published local posts for a location. |
| `deleteLocalPost( string $name )`                  | `void`           | Delete a specific local post. |

### Media

- **Client**: `ArtisanPackUI\GoogleBusinessProfile\Media\MediaClient`

| Method                                                          | Returns             | Purpose |
|-----------------------------------------------------------------|---------------------|---------|
| `createMediaItem( string $parent, array $mediaItem )`           | `MediaItem`         | Attach a media item to a location (single-shot, source-URL mode). |
| `startUpload( string $parent )`                                 | `MediaItemDataRef`  | Kick off a resumable upload session and receive the upload URL. |
| `uploadMediaBytes( MediaItemDataRef $ref, string $bytes, … )`   | `void`              | Upload raw bytes into a resumable session started with `startUpload()`. |
| `uploadMediaItem( string $parent, array $mediaItem, string $bytes, … )` | `MediaItem` | Convenience wrapper that runs `startUpload()`, `uploadMediaBytes()`, and `createMediaItem()` in sequence. |

The upload flow talks to a second host —
`https://mybusiness.googleapis.com/upload/v1/media/{resourceName}` — but
the client hides that detail; callers only interact with `MediaClient`.

[gbp-v4]: https://developers.google.com/my-business/reference/rest/v4

## 4. Performance

- **Host**: `https://businessprofileperformance.googleapis.com/v1`
- **Client**: `ArtisanPackUI\GoogleBusinessProfile\Performance\PerformanceClient`
- **Google docs**: [Business Profile Performance API][gbp-perf]

| Method                                                      | Returns                        | Purpose |
|-------------------------------------------------------------|--------------------------------|---------|
| `getDailyMetricsTimeSeries( string $name, string $metric, … )` | `DailyMetricTimeSeries`     | Fetch a single daily metric for one location across a date range. |
| `fetchMultiDailyMetricsTimeSeries( string $location, array $metrics, … )` | `MultiDailyMetricTimeSeries` | Fetch multiple daily metrics for one location in a single call. |

[gbp-perf]: https://developers.google.com/my-business/reference/performance/rest

## Shared behaviour

Every method above:

- Delegates authentication to the injected `TokenProvider` (see
  [[token-provider]]).
- Retries `HTTP 429`, `HTTP 5xx`, and `ConnectionException` up to three
  times by default with a 250ms sleep between attempts.
- Maps every non-2xx response and every terminal `ConnectionException` to
  `ArtisanPackUI\GoogleBusinessProfile\Exceptions\ApiException`.
- Decodes the JSON response into a typed DTO under the family's
  `DataTransferObjects/` namespace.

See [[testing]] for how to exercise these methods under `Http::fake()`.
