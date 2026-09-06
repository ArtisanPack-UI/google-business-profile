# ArtisanPack UI Google Business Profile Changelog

## Unreleased

### Added

- Account Management API client (`AccountManagementClient::listAccounts()`)
  with typed `Account` and `AccountList` DTOs. Accepts `pageSize`,
  `pageToken`, `filter`, and `parentAccount` query parameters; rejects
  a `pageSize` outside Google's documented `1..20` range with an
  `InvalidArgumentException` before dispatching; preserves the raw
  account payload on the DTO for forward compatibility.
