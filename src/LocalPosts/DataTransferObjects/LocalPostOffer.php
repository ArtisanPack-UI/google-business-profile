<?php

/**
 * Offer DTO for the Google Business Profile v4 Local Posts API.
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
 * Immutable representation of a local post's offer details.
 *
 * Mirrors the `LocalPostOffer` sub-resource on a v4 `LocalPost`. The
 * sub-resource is only emitted when the post's `topicType` is `OFFER`; on
 * other topic types this DTO is absent from {@see LocalPost::$offer}.
 *
 * @package    ArtisanPack_UI
 * @subpackage GoogleBusinessProfile
 *
 * @since      1.0.0
 */
final class LocalPostOffer
{
    /**
     * Construct a LocalPostOffer DTO.
     *
     * @since 1.0.0
     *
     * @param  string|null  $couponCode  Coupon code the user redeems, or
     *                                   null when the offer has no code.
     * @param  string|null  $redeemOnlineUrl  URL the user visits to redeem
     *                                        online, or null when the offer
     *                                        is not redeemable online.
     * @param  string|null  $termsConditions  Free-form terms and conditions
     *                                        text, or null when absent.
     */
    public function __construct(
        public readonly ?string $couponCode = null,
        public readonly ?string $redeemOnlineUrl = null,
        public readonly ?string $termsConditions = null,
    ) {
    }

    /**
     * Build a LocalPostOffer from an API payload.
     *
     * Missing fields remain null so a partial payload does not poison an
     * otherwise-usable local post.
     *
     * @since 1.0.0
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray( array $data ): self
    {
        $couponCode = null;

        if ( isset( $data['couponCode'] ) && '' !== $data['couponCode'] ) {
            $couponCode = (string) $data['couponCode'];
        }

        $redeemOnlineUrl = null;

        if ( isset( $data['redeemOnlineUrl'] ) && '' !== $data['redeemOnlineUrl'] ) {
            $redeemOnlineUrl = (string) $data['redeemOnlineUrl'];
        }

        $termsConditions = null;

        if ( isset( $data['termsConditions'] ) && '' !== $data['termsConditions'] ) {
            $termsConditions = (string) $data['termsConditions'];
        }

        return new self( $couponCode, $redeemOnlineUrl, $termsConditions );
    }
}
