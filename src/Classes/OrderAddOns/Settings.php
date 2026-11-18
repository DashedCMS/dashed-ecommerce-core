<?php

namespace Dashed\DashedEcommerceCore\Classes\OrderAddOns;

use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Classes\Orders;

/**
 * Instellingen van bijbestellen per site (Instellingen → Bijbestellen). Alle getters
 * geven een geldige waarde terug: ontbrekende of foute waarden vallen terug op de
 * standaard, getallen worden begrensd.
 */
final class Settings
{
    public const ENABLED = 'order_add_ons_enabled';

    public const OPEN_STATUSES = 'order_add_ons_open_statuses';

    public const MAX_HOURS = 'order_add_ons_max_hours';

    public const MAIL_DELAY_MINUTES = 'order_add_ons_mail_delay_minutes';

    public const MAIL_COOLDOWN_DAYS = 'order_add_ons_mail_cooldown_days';

    public const ENTRY_POINTS = 'order_add_ons_entry_points';

    public const DEFAULT_OPEN_STATUSES = ['unhandled'];

    public const DEFAULT_MAX_HOURS = 24;

    public const MAX_MAX_HOURS = 720;

    public const DEFAULT_MAIL_DELAY_MINUTES = 60;

    public const MIN_MAIL_DELAY_MINUTES = 5;

    public const DEFAULT_MAIL_COOLDOWN_DAYS = 30;

    public const MAX_MAIL_COOLDOWN_DAYS = 365;

    public const ENTRY_THANK_YOU = 'thank_you';

    public const ENTRY_CONFIRMATION_MAIL = 'confirmation_mail';

    public const ENTRY_REMINDER_MAIL = 'reminder_mail';

    public const ENTRY_TRACKER = 'tracker';

    /** @return array<string, string> ingang => label */
    public static function entryPointOptions(): array
    {
        return [
            self::ENTRY_THANK_YOU => 'Bedankpagina',
            self::ENTRY_CONFIRMATION_MAIL => 'Orderbevestiging',
            self::ENTRY_REMINDER_MAIL => 'Herinneringsmail',
            self::ENTRY_TRACKER => 'Ordertracker/account',
        ];
    }

    public static function enabled(?string $siteId = null): bool
    {
        return filter_var(Customsetting::get(self::ENABLED, $siteId, '0'), FILTER_VALIDATE_BOOLEAN);
    }

    /** @return array<int, string> fulfillment statussen waarin bijbestellen nog kan */
    public static function openStatuses(?string $siteId = null): array
    {
        $statuses = self::readList(self::OPEN_STATUSES, $siteId);
        if ($statuses === null) {
            return self::DEFAULT_OPEN_STATUSES;
        }

        return array_values(array_intersect($statuses, array_keys(Orders::getFulfillmentStatusses())));
    }

    /** Hoeveel uur na betaling het aanbod hoogstens open is; 0 = geen limiet. */
    public static function maxHours(?string $siteId = null): int
    {
        return self::readInt(self::MAX_HOURS, $siteId, self::DEFAULT_MAX_HOURS, 0, self::MAX_MAX_HOURS);
    }

    /** Hoeveel minuten na betaling de herinneringsmail gaat. */
    public static function mailDelayMinutes(?string $siteId = null): int
    {
        return self::readInt(self::MAIL_DELAY_MINUTES, $siteId, self::DEFAULT_MAIL_DELAY_MINUTES, self::MIN_MAIL_DELAY_MINUTES, null);
    }

    /** Niet nog een herinneringsmail aan hetzelfde adres binnen zoveel dagen. */
    public static function mailCooldownDays(?string $siteId = null): int
    {
        return self::readInt(self::MAIL_COOLDOWN_DAYS, $siteId, self::DEFAULT_MAIL_COOLDOWN_DAYS, 0, self::MAX_MAIL_COOLDOWN_DAYS);
    }

    /** @return array<int, string> ingeschakelde ingangen */
    public static function entryPoints(?string $siteId = null): array
    {
        $points = self::readList(self::ENTRY_POINTS, $siteId);
        if ($points === null) {
            return array_keys(self::entryPointOptions());
        }

        return array_values(array_intersect($points, array_keys(self::entryPointOptions())));
    }

    public static function entryPointEnabled(string $entryPoint, ?string $siteId = null): bool
    {
        return in_array($entryPoint, self::entryPoints($siteId), true);
    }

    /**
     * Slaat de instellingen van één site op, begrensd zoals de getters ze lezen.
     *
     * @param array{enabled?: bool, open_statuses?: array, max_hours?: int|string|null, mail_delay_minutes?: int|string|null, mail_cooldown_days?: int|string|null, entry_points?: array} $values
     */
    public static function save(array $values, string $siteId): void
    {
        if (array_key_exists('enabled', $values)) {
            Customsetting::set(self::ENABLED, (bool) $values['enabled'], $siteId);
        }
        if (array_key_exists('open_statuses', $values)) {
            Customsetting::set(self::OPEN_STATUSES, array_values(array_intersect((array) $values['open_statuses'], array_keys(Orders::getFulfillmentStatusses()))), $siteId);
        }
        if (array_key_exists('max_hours', $values)) {
            Customsetting::set(self::MAX_HOURS, self::clamp($values['max_hours'], self::DEFAULT_MAX_HOURS, 0, self::MAX_MAX_HOURS), $siteId);
        }
        if (array_key_exists('mail_delay_minutes', $values)) {
            Customsetting::set(self::MAIL_DELAY_MINUTES, self::clamp($values['mail_delay_minutes'], self::DEFAULT_MAIL_DELAY_MINUTES, self::MIN_MAIL_DELAY_MINUTES, null), $siteId);
        }
        if (array_key_exists('mail_cooldown_days', $values)) {
            Customsetting::set(self::MAIL_COOLDOWN_DAYS, self::clamp($values['mail_cooldown_days'], self::DEFAULT_MAIL_COOLDOWN_DAYS, 0, self::MAX_MAIL_COOLDOWN_DAYS), $siteId);
        }
        if (array_key_exists('entry_points', $values)) {
            Customsetting::set(self::ENTRY_POINTS, array_values(array_intersect((array) $values['entry_points'], array_keys(self::entryPointOptions()))), $siteId);
        }
    }

    private static function readInt(string $key, ?string $siteId, int $default, int $min, ?int $max): int
    {
        return self::clamp(Customsetting::get($key, $siteId), $default, $min, $max);
    }

    private static function clamp(mixed $value, int $default, int $min, ?int $max): int
    {
        if (! is_numeric($value)) {
            return $default;
        }

        $value = max($min, (int) $value);

        return $max === null ? $value : min($max, $value);
    }

    /** Null = nooit ingesteld (val terug op de standaard); een lege lijst is een bewuste keuze. */
    private static function readList(string $key, ?string $siteId): ?array
    {
        $value = Customsetting::get($key, $siteId);

        if (is_string($value)) {
            if ($value === '') {
                return null;
            }
            $value = json_decode($value, true);
        }

        return is_array($value) ? array_values(array_filter($value, 'is_string')) : null;
    }
}
