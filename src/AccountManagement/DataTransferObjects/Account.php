<?php

/**
 * Account DTO for the Google Business Profile Account Management API.
 *
 * @package    ArtisanPack_UI
 * @subpackage GoogleBusinessProfile
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\GoogleBusinessProfile\AccountManagement\DataTransferObjects;

/**
 * Immutable representation of a single Google Business Profile account.
 *
 * Mirrors the `Account` resource returned by the Account Management API's
 * `accounts.list` endpoint. Enum-shaped fields (type, role, verification
 * state, vetted state, permission level) are kept as plain strings so
 * callers see whatever value the API returned — including values Google
 * may add later — without the DTO layer silently dropping them.
 *
 * The raw response is preserved on {@see self::$raw} so consumers can reach
 * fields the typed surface does not yet expose (for example, the
 * `organizationInfo` sub-resource) without a round-trip.
 *
 * @package    ArtisanPack_UI
 * @subpackage GoogleBusinessProfile
 *
 * @since      1.0.0
 */
final class Account
{
    /**
     * Construct an Account DTO.
     *
     * @since 1.0.0
     *
     * @param  string  $name  Resource name (`accounts/{accountId}`).
     * @param  string  $accountName  Human-readable account name.
     * @param  string|null  $type  Account type (e.g. `PERSONAL`,
     *                             `LOCATION_GROUP`, `USER_GROUP`,
     *                             `ORGANIZATION`), or null when absent.
     * @param  string|null  $role  Caller's role on the account (e.g.
     *                             `OWNER`, `CO_OWNER`, `MANAGER`,
     *                             `SITE_MANAGER`), or null when absent.
     * @param  string|null  $verificationState  Verification state (e.g.
     *                                          `VERIFIED`, `UNVERIFIED`),
     *                                          or null when absent.
     * @param  string|null  $vettedState  Vetted state (e.g. `NOT_VETTED`,
     *                                    `VETTED`, `INVALID`), or null
     *                                    when absent.
     * @param  string|null  $accountNumber  Account number, or null when
     *                                      absent.
     * @param  string|null  $permissionLevel  Permission level (e.g.
     *                                        `OWNER_LEVEL`,
     *                                        `MEMBER_LEVEL`), or null
     *                                        when absent.
     * @param  array<string, mixed>  $raw  The raw payload for this
     *                                     account exactly as returned by
     *                                     the API.
     */
    public function __construct(
        public readonly string $name,
        public readonly string $accountName,
        public readonly ?string $type = null,
        public readonly ?string $role = null,
        public readonly ?string $verificationState = null,
        public readonly ?string $vettedState = null,
        public readonly ?string $accountNumber = null,
        public readonly ?string $permissionLevel = null,
        public readonly array $raw = [],
    ) {
    }

    /**
     * Build an Account from an API payload.
     *
     * Missing fields are coerced to sensible defaults rather than throwing:
     * only `name` is guaranteed by the API for a listed account, and even
     * the human-readable `accountName` may be omitted on some account
     * types. Unknown fields survive on {@see self::$raw}.
     *
     * @since 1.0.0
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray( array $data ): self
    {
        return new self(
            name             : isset( $data['name'] ) ? (string) $data['name'] : '',
            accountName      : isset( $data['accountName'] ) ? (string) $data['accountName'] : '',
            type             : isset( $data['type'] ) ? (string) $data['type'] : null,
            role             : isset( $data['role'] ) ? (string) $data['role'] : null,
            verificationState: isset( $data['verificationState'] ) ? (string) $data['verificationState'] : null,
            vettedState      : isset( $data['vettedState'] ) ? (string) $data['vettedState'] : null,
            accountNumber    : isset( $data['accountNumber'] ) ? (string) $data['accountNumber'] : null,
            permissionLevel  : isset( $data['permissionLevel'] ) ? (string) $data['permissionLevel'] : null,
            raw              : $data,
        );
    }
}
