<?php

namespace Dashed\DashedEcommerceCore\Services\Quotes;

use Dashed\DashedCore\Classes\Sites;
use Dashed\DashedCore\Models\Customsetting;

/**
 * Alle offerte-instellingen op een plek, zodat het scherm, de PDF, de mail en
 * de commando's dezelfde standaarden lezen. Sleutelnamen zijn per site.
 */
class QuoteDefaults
{
    public const DEFAULT_NUMBER_FORMAT = 'OFF-:jaar:-:nummer:';

    public static function numberFormat(?string $siteId = null): string
    {
        $value = (string) Customsetting::get('quote_number_format', $siteId ?: Sites::getActive(), self::DEFAULT_NUMBER_FORMAT);

        return $value !== '' ? $value : self::DEFAULT_NUMBER_FORMAT;
    }

    public static function validityDays(?string $siteId = null): int
    {
        return max(1, (int) Customsetting::get('quote_validity_days', $siteId ?: Sites::getActive(), 14));
    }

    /** @param 'intro'|'terms'|'acceptance' $key */
    public static function text(string $key, string $locale, ?string $siteId = null): string
    {
        return (string) Customsetting::get('quote_'.$key.'_'.$locale, $siteId ?: Sites::getActive(), '');
    }

    public static function reminderEnabled(?string $siteId = null): bool
    {
        return (bool) Customsetting::get('quote_reminder_enabled', $siteId ?: Sites::getActive(), false);
    }

    public static function reminderDaysBefore(?string $siteId = null): int
    {
        return max(1, (int) Customsetting::get('quote_reminder_days_before', $siteId ?: Sites::getActive(), 3));
    }

    public static function fromEmail(?string $siteId = null): ?string
    {
        $value = (string) Customsetting::get('quote_from_email', $siteId ?: Sites::getActive(), '');

        return $value !== '' ? $value : null;
    }
}
