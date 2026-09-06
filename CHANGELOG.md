# ArtisanPack UI Google Business Profile Changelog

## Unreleased

### Added

- Account Management API client (`AccountManagementClient::listAccounts()`)
  with typed `Account` and `AccountList` DTOs. Accepts `pageSize`,
  `pageToken`, `filter`, and `parentAccount` query parameters; clamps
  `pageSize` to Google's documented `1..20` range; preserves the raw
  account payload on the DTO for forward compatibility.
