<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Services\ProductReviews;

use Dashed\DashedCore\Classes\Sites;
use Dashed\DashedCore\Models\Customsetting;

/**
 * De instellingen van productreviews en productvragen, per site. Alles staat
 * standaard uit: reviews tonen, het verzoek na levering en vragen stellen
 * zijn drie losse schakelaars.
 */
class ProductReviewSettings
{
    public const DEFAULT_DELAY_DAYS = 7;

    /** Reviews tonen op de productpagina, in het productschema, en via de link insturen. */
    public static function reviewsEnabled(?string $siteId = null): bool
    {
        return (bool) Customsetting::get('product_reviews_enabled', $siteId ?? Sites::getActive(), '0');
    }

    /** Na levering een mail met een reviewlink per gekocht product. */
    public static function requestsEnabled(?string $siteId = null): bool
    {
        return self::reviewsEnabled($siteId)
            && (bool) Customsetting::get('product_reviews_request_enabled', $siteId ?? Sites::getActive(), '0');
    }

    public static function delayDays(?string $siteId = null): int
    {
        $value = Customsetting::get('product_reviews_request_delay_days', $siteId ?? Sites::getActive(), (string) self::DEFAULT_DELAY_DAYS);

        return max(0, (int) ($value === '' || $value === null ? self::DEFAULT_DELAY_DAYS : $value));
    }

    public static function questionsEnabled(?string $siteId = null): bool
    {
        return (bool) Customsetting::get('product_questions_enabled', $siteId ?? Sites::getActive(), '0');
    }

    /** Hoeveel dagen de reviewlink in de mail geldig is. */
    public static function linkValidDays(): int
    {
        return 90;
    }
}
