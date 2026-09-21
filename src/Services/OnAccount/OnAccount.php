<?php

namespace Dashed\DashedEcommerceCore\Services\OnAccount;

use Illuminate\Support\Facades\DB;
use Dashed\DashedCore\Models\User;
use Dashed\DashedEcommerceCore\Models\PaymentMethod;

/**
 * Mag deze klant nu met deze methode op rekening bestellen? De enige plek
 * die dat beslist: checkout, proforma, CMS en POS vragen het hier.
 *
 * Een methode op rekening moet expliciet aan de klant gekoppeld zijn, ook
 * als hij aan niemand gekoppeld is. Bij gewone methodes betekent "aan
 * niemand gekoppeld" juist "voor iedereen"; voor krediet is dat te ruim.
 */
class OnAccount
{
    public const NOT_LOGGED_IN = 'not_logged_in';

    public const NOT_ENABLED = 'not_enabled';

    public const BLOCKED_MANUAL = 'blocked_manual';

    public const BLOCKED_OVERDUE = 'blocked_overdue';

    public const OVER_LIMIT = 'over_limit';

    public static function check(?User $user, PaymentMethod $method, float $cartTotal): OnAccountCheck
    {
        if (! $method->on_account) {
            return new OnAccountCheck(true);
        }

        if (! $user) {
            return new OnAccountCheck(false, self::NOT_LOGGED_IN);
        }

        if (! self::enabledFor($user, $method)) {
            return new OnAccountCheck(false, self::NOT_ENABLED);
        }

        if ($user->on_account_blocked_at) {
            return new OnAccountCheck(false, self::BLOCKED_MANUAL);
        }

        $blockAfter = OnAccountSettings::blockAfterDays();
        if ($blockAfter > 0 && OnAccountBalance::hasOverdue($user, $blockAfter)) {
            return new OnAccountCheck(false, self::BLOCKED_OVERDUE);
        }

        $limit = OnAccountSettings::creditLimitFor($user);
        if ($limit !== null && round(OnAccountBalance::open($user) + $cartTotal, 2) > round($limit, 2)) {
            return new OnAccountCheck(false, self::OVER_LIMIT);
        }

        return new OnAccountCheck(true);
    }

    public static function enabledFor(User $user, PaymentMethod $method): bool
    {
        return DB::table('dashed__payment_method_users')
            ->where('payment_method_id', $method->id)
            ->where('user_id', $user->id)
            ->exists();
    }

    public static function unavailableNotice(?User $user, float $cartTotal): ?string
    {
        if (! $user) {
            return null;
        }

        $methods = PaymentMethod::query()
            ->where('on_account', true)
            ->where('active', 1)
            ->whereIn('id', DB::table('dashed__payment_method_users')->where('user_id', $user->id)->select('payment_method_id'))
            ->get();

        foreach ($methods as $method) {
            $check = self::check($user, $method, $cartTotal);
            if (! $check->allowed && $check->message()) {
                return $check->message();
            }
        }

        return null;
    }
}
