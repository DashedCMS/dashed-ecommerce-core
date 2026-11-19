<?php

namespace Dashed\DashedEcommerceCore\CustomerProfile\Sections;

use Dashed\DashedEcommerceCore\Models\Referral;
use Dashed\DashedEcommerceCore\Models\ReferralCode;
use Dashed\DashedEcommerceCore\Services\Referrals\ReferralProgram;

/**
 * Verwijscode van de klant en wie hij doorverwees. Verborgen als hij nog
 * nooit een code had en zelf ook niet via een verwijzing binnenkwam.
 */
class ReferralSection
{
    public static function label(): string
    {
        return __('Verwijsprogramma');
    }

    public static function sort(): int
    {
        return 40;
    }

    public static function forCustomer(?int $userId, ?string $email): ?array
    {
        $codes = ReferralCode::query()
            ->where(fn ($q) => $q->whereRaw('1 = 0')
                ->when($userId, fn ($q) => $q->orWhere('user_id', $userId))
                ->when($email, fn ($q) => $q->orWhere('email', $email)))
            ->get();

        $referredBy = $email
            ? Referral::query()->where('referred_email', $email)->with('referralCode')->first()
            : null;

        if ($codes->isEmpty() && ! $referredBy) {
            return null;
        }

        $referrals = Referral::query()
            ->whereIn('referral_code_id', $codes->pluck('id'))
            ->latest('id')
            ->limit(25)
            ->get();

        $stats = [];
        foreach ($codes as $code) {
            $stats[] = ['label' => __('Verwijslink'), 'value' => ReferralProgram::url($code)];
        }
        $stats[] = ['label' => __('Verwijzingen'), 'value' => (string) $referrals->count()];
        $stats[] = ['label' => __('Beloond'), 'value' => (string) $referrals->where('status', Referral::STATUS_REWARDED)->count()];

        if ($referredBy) {
            $stats[] = ['label' => __('Zelf doorverwezen door'), 'value' => (string) $referredBy->referralCode?->email];
        }

        return [
            'stats' => $stats,
            'columns' => [
                'date' => __('Datum'),
                'friend' => __('Nieuwe klant'),
                'status' => __('Status'),
            ],
            'rows' => $referrals->map(fn ($referral) => [
                'date' => $referral->created_at?->format('d-m-Y'),
                'friend' => (string) $referral->referred_email,
                'status' => (string) (Referral::statuses()[$referral->status] ?? $referral->status)
                    . ($referral->reject_reason ? ': ' . (Referral::rejectReasons()[$referral->reject_reason] ?? $referral->reject_reason) : ''),
            ])->all(),
            'empty' => __('Nog niemand doorverwezen.'),
        ];
    }
}
