<?php

namespace Dashed\DashedEcommerceCore\CustomerProfile\Sections;

use Dashed\DashedCore\Models\User;
use Dashed\DashedEcommerceCore\Classes\CurrencyHelper;
use Dashed\DashedEcommerceCore\Services\OnAccount\OnAccountBalance;

/**
 * Openstaand saldo op rekening, uit OnAccountBalance. Alleen voor een
 * account: een gast kan niet op rekening bestellen.
 */
class OnAccountSection
{
    public static function label(): string
    {
        return __('Op rekening');
    }

    public static function sort(): int
    {
        return 15;
    }

    public static function forCustomer(?int $userId, ?string $email): ?array
    {
        $user = $userId ? User::find($userId) : null;

        if (! $user) {
            return null;
        }

        $open = OnAccountBalance::open($user);
        $overdue = OnAccountBalance::overdue($user);

        if ($open <= 0.004 && $overdue <= 0.004) {
            return null;
        }

        return [
            'stats' => [
                ['label' => __('Openstaand'), 'value' => CurrencyHelper::formatPrice($open)],
                ['label' => __('Waarvan verlopen'), 'value' => CurrencyHelper::formatPrice($overdue)],
            ],
        ];
    }
}
