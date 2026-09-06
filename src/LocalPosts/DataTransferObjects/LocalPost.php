<?php

/**
 * LocalPost DTO for the Google Business Profile v4 Local Posts API.
 *
 * @package    ArtisanPack_UI
 * @subpackage GoogleBusinessProfile
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\GoogleBusinessProfile\LocalPosts\DataTransferObjects;

/**
 * Immutable representation of a single Google Business Profile local post.
 *
 * Mirrors the `LocalPost` resource returned by the v4 Local Posts API's
 * `accounts.locations.localPosts.create` and
 * `accounts.locations.localPosts.list` endpoints. Enum-shaped fields
 * (`state`, `topicType`, `alertType`) are kept as plain strings so callers
 * see whatever value the API returned — including values Google may add
 * later — without the DTO layer silently dropping them.
 *
 * The full post payload — every field the API returned, whether or not it
 * also appears as a typed property — is preserved on {@see self::$raw} so
 * consumers can reach fields the typed surface does not yet expose without
 * a round-trip. Because typed fields are duplicated on `$raw`, treat it as
 * the source of truth for the wire payload rather than as a "leftovers"
 * bag.
 *
 * Sub-resources that the API only emits for specific topic types (event
 * schedule, offer details) are null on posts of other types, matching the
 * wire shape.
 *
 * @package    ArtisanPack_UI
 * @subpackage GoogleBusinessProfile
 *
 * @since      1.0.0
 */
final class LocalPost
{
    /**
     * Construct a LocalPost DTO.
     *
     * @since 1.0.0
     *
     * @param  string  $name  Resource name
     *                        (`accounts/{a}/locations/{l}/localPosts/{p}`).
     * @param  string|null  $languageCode  BCP-47 language code of the post's
     *                                     text (e.g. `en`), or null when
     *                                     absent.
     * @param  string|null  $summary  Post summary text, or null when
     *                                absent (posts with only media are
     *                                permitted).
     * @param  CallToAction|null  $callToAction  The post's CTA button, or
     *                                           null when the API omitted
     *                                           the sub-resource.
     * @param  string|null  $createTime  RFC 3339 timestamp for when the
     *                                   post was created, or null.
     * @param  string|null  $updateTime  RFC 3339 timestamp for the last
     *                                   post edit, or null.
     * @param  LocalPostEvent|null  $event  Event schedule sub-resource,
     *                                      emitted for `EVENT` and `OFFER`
     *                                      topic types only.
     * @param  string|null  $state  State enum value (e.g. `LIVE`,
     *                              `REJECTED`, `PROCESSING`), or null.
     * @param  list<MediaItem>  $media  Media items attached to the post.
     * @param  string|null  $searchUrl  Public URL of the post on Google
     *                                  Search / Maps, or null.
     * @param  string|null  $topicType  Topic type enum value (`STANDARD`,
     *                                  `EVENT`, `OFFER`, `ALERT`), or null.
     * @param  string|null  $alertType  Alert-type enum value populated only
     *                                  when `topicType` is `ALERT`, or null.
     * @param  LocalPostOffer|null  $offer  Offer details sub-resource,
     *                                      emitted for `OFFER` topic types
     *                                      only.
     * @param  array<string, mixed>  $raw  The raw payload for this post
     *                                     exactly as returned by the API.
     */
    public function __construct(
        public readonly string $name,
        public readonly ?string $languageCode = null,
        public readonly ?string $summary = null,
        public readonly ?CallToAction $callToAction = null,
        public readonly ?string $createTime = null,
        public readonly ?string $updateTime = null,
        public readonly ?LocalPostEvent $event = null,
        public readonly ?string $state = null,
        public readonly array $media = [],
        public readonly ?string $searchUrl = null,
        public readonly ?string $topicType = null,
        public readonly ?string $alertType = null,
        public readonly ?LocalPostOffer $offer = null,
        public readonly array $raw = [],
    ) {
    }

    /**
     * Build a LocalPost from an API payload.
     *
     * Missing fields are coerced to sensible defaults rather than throwing:
     * only `name` is guaranteed by the API for a stored post. Sub-resources
     * that are absent from the wire payload remain null. A `media` value
     * that is not an array is treated as an empty list. Unknown fields
     * survive on {@see self::$raw}.
     *
     * @since 1.0.0
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray( array $data ): self
    {
        $callToAction = null;

        if ( isset( $data['callToAction'] ) && is_array( $data['callToAction'] ) ) {
            $callToAction = CallToAction::fromArray( $data['callToAction'] );
        }

        $event = null;

        if ( isset( $data['event'] ) && is_array( $data['event'] ) ) {
            $event = LocalPostEvent::fromArray( $data['event'] );
        }

        $offer = null;

        if ( isset( $data['offer'] ) && is_array( $data['offer'] ) ) {
            $offer = LocalPostOffer::fromArray( $data['offer'] );
        }

        $media = [];

        if ( isset( $data['media'] ) && is_array( $data['media'] ) ) {
            foreach ( $data['media'] as $rawMedia ) {
                if ( is_array( $rawMedia ) && ! array_is_list( $rawMedia ) ) {
                    $media[] = MediaItem::fromArray( $rawMedia );
                }
            }
        }

        return new self(
            name        : isset( $data['name'] ) ? (string) $data['name'] : '',
            languageCode: isset( $data['languageCode'] ) && '' !== $data['languageCode'] ? (string) $data['languageCode'] : null,
            summary     : isset( $data['summary'] ) && '' !== $data['summary'] ? (string) $data['summary'] : null,
            callToAction: $callToAction,
            createTime  : isset( $data['createTime'] ) && '' !== $data['createTime'] ? (string) $data['createTime'] : null,
            updateTime  : isset( $data['updateTime'] ) && '' !== $data['updateTime'] ? (string) $data['updateTime'] : null,
            event       : $event,
            state       : isset( $data['state'] ) && '' !== $data['state'] ? (string) $data['state'] : null,
            media       : $media,
            searchUrl   : isset( $data['searchUrl'] ) && '' !== $data['searchUrl'] ? (string) $data['searchUrl'] : null,
            topicType   : isset( $data['topicType'] ) && '' !== $data['topicType'] ? (string) $data['topicType'] : null,
            alertType   : isset( $data['alertType'] ) && '' !== $data['alertType'] ? (string) $data['alertType'] : null,
            offer       : $offer,
            raw         : $data,
        );
    }

    /**
     * Whether this post carries a call-to-action button.
     *
     * @since 1.0.0
     */
    public function hasCallToAction(): bool
    {
        return null !== $this->callToAction;
    }

    /**
     * Whether this post carries any attached media items.
     *
     * @since 1.0.0
     */
    public function hasMedia(): bool
    {
        return [] !== $this->media;
    }
}
