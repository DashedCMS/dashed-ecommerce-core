<?php

namespace Dashed\DashedEcommerceCore\Services\Referrals;

use Dashed\DashedCore\Classes\Sites;
use Dashed\DashedCore\Models\Customsetting;

/**
 * Instellingen van het verwijsprogramma, per site. Standaard uit.
 */
class ReferralSettings
{
    /**
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [
            'referral_enabled' => false,
            'referral_friend_discount_type' => 'percentage',
            'referral_friend_discount_value' => 10,
            'referral_friend_minimum_amount' => 0,
            'referral_reward_type' => 'amount',
            'referral_reward_value' => 10,
            'referral_reward_valid_days' => 90,
            'referral_wait_days' => 14,
        ];
    }

    public static function get(string $key, ?string $siteId = null): mixed
    {
        return Customsetting::get($key, $siteId ?: Sites::getActive(), self::defaults()[$key] ?? null);
    }

    public static function enabled(?string $siteId = null): bool
    {
        return (bool) self::get('referral_enabled', $siteId);
    }
}
