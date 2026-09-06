<?php

/**
 * DailyMetric enum for the Google Business Profile Performance API.
 *
 * @package    ArtisanPack_UI
 * @subpackage GoogleBusinessProfile
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\GoogleBusinessProfile\Performance\DataTransferObjects;

/**
 * The set of daily metrics the Business Profile Performance API can report.
 *
 * The wire values match the API's `DailyMetric` enum exactly and are the
 * strings the client sends as the `dailyMetric` / `dailyMetrics` query
 * parameters. Case names follow the ArtisanPack UI TitleCase convention.
 *
 * The four `BusinessImpressions*` cases correspond to Google's split of
 * impressions across surface (Search / Maps) and device (Desktop / Mobile);
 * the remaining cases cover the discrete action metrics the API exposes.
 *
 * @package    ArtisanPack_UI
 * @subpackage GoogleBusinessProfile
 *
 * @since      1.0.0
 */
enum DailyMetric: string
{
    case BusinessImpressionsDesktopMaps = 'BUSINESS_IMPRESSIONS_DESKTOP_MAPS';

    case BusinessImpressionsDesktopSearch = 'BUSINESS_IMPRESSIONS_DESKTOP_SEARCH';

    case BusinessImpressionsMobileMaps = 'BUSINESS_IMPRESSIONS_MOBILE_MAPS';

    case BusinessImpressionsMobileSearch = 'BUSINESS_IMPRESSIONS_MOBILE_SEARCH';

    case BusinessConversations = 'BUSINESS_CONVERSATIONS';

    case BusinessDirectionRequests = 'BUSINESS_DIRECTION_REQUESTS';

    case CallClicks = 'CALL_CLICKS';

    case WebsiteClicks = 'WEBSITE_CLICKS';

    case BusinessBookings = 'BUSINESS_BOOKINGS';

    case BusinessFoodOrders = 'BUSINESS_FOOD_ORDERS';

    case BusinessFoodMenuClicks = 'BUSINESS_FOOD_MENU_CLICKS';
}
