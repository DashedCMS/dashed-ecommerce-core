<?php

namespace Dashed\DashedEcommerceCore\Services\Referrals;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Dashed\DashedCore\Classes\Sites;
use Illuminate\Database\Eloquent\Builder;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\Referral;
use Dashed\DashedEcommerceCore\Models\OrderLog;
use Dashed\DashedTranslations\Models\Translation;
use Dashed\DashedEcommerceCore\Models\OrderReturn;
use Dashed\DashedEcommerceCore\Models\DiscountCode;
use Dashed\DashedEcommerceCore\Models\ReferralCode;
use Dashed\DashedEcommerceCore\Mail\ReferralRewardMail;

/**
 * Het verwijsprogramma. Een klant deelt zijn eigen code; die code is ook een
 * kortingscode voor de nieuwe klant. Na betaling van die eerste bestelling
 * ontstaat een Referral, en na de wachttijd (de retourtermijn) krijgt de
 * verwijzer een eigen kortingscode van één keer gebruik.
 *
 * Misbruik wordt op drie plekken tegengehouden: de checkout weigert de code
 * voor de verwijzer zelf en voor wie al eerder bestelde (checkoutRefusal()),
 * bij betaling wordt een verwijzing met hetzelfde e-mailadres, adres of
 * IP-adres als de verwijzer afgewezen (recordPaidOrder()), en na de
 * wachttijd krijgt een geretourneerde of geannuleerde bestelling geen
 * beloning (processDue()).
 */
class ReferralProgram
{
    public const PAID_STATUSES = ['paid', 'waiting_for_confirmation', 'partially_paid'];

    public static function enabled(?string $siteId = null): bool
    {
        return ReferralSettings::enabled($siteId);
    }

    /**
     * De eigen verwijscode van een klant, aangemaakt bij de eerste vraag.
     * Gekoppeld aan het e-mailadres (ook voor gasten), met het account erbij
     * als dat er is.
     */
    public static function codeFor(string $email, ?int $userId = null, ?string $siteId = null): ReferralCode
    {
        $siteId = $siteId ?: Sites::getActive();
        $email = mb_strtolower(trim($email));

        $referralCode = ReferralCode::query()
            ->where('site_id', $siteId)
            ->where('email', $email)
            ->first();

        if (! $referralCode) {
            $referralCode = ReferralCode::create([
                'site_id' => $siteId,
                'user_id' => $userId,
                'email' => $email,
                'code' => self::uniqueCode('VRIEND'),
            ]);
        } elseif ($userId && ! $referralCode->user_id) {
            $referralCode->update(['user_id' => $userId]);
        }

        self::syncDiscountCode($referralCode);

        return $referralCode;
    }

    public static function url(ReferralCode $referralCode): string
    {
        return route('dashed.frontend.referral', ['code' => $referralCode->code]);
    }

    /**
     * De verwijslink voor een bestelling, of leeg als het programma op die
     * site uit staat of de bestelling niet van ons is (Bol, Etsy).
     */
    public static function urlForOrder(Order $order): string
    {
        if (! $order->email || ! self::enabled($order->site_id) || ! in_array((string) ($order->order_origin ?: 'own'), ['own', 'pos'], true)) {
            return '';
        }

        return self::url(self::codeFor($order->email, $order->user_id, $order->site_id));
    }

    public static function findByCode(?string $code): ?ReferralCode
    {
        if (! $code) {
            return null;
        }

        return ReferralCode::query()->where('code', strtoupper(trim($code)))->first();
    }

    public static function forDiscountCode(?DiscountCode $discountCode): ?ReferralCode
    {
        if (! $discountCode) {
            return null;
        }

        return ReferralCode::query()->where('discount_code_id', $discountCode->id)->first();
    }

    /**
     * Houdt de kortingscode van de nieuwe klant gelijk aan de instellingen
     * van nu, zodat een gewijzigde korting ook voor bestaande codes geldt.
     */
    public static function syncDiscountCode(ReferralCode $referralCode): DiscountCode
    {
        $siteId = $referralCode->site_id;
        $type = ReferralSettings::get('referral_friend_discount_type', $siteId) === 'amount' ? 'amount' : 'percentage';
        $value = (float) ReferralSettings::get('referral_friend_discount_value', $siteId);
        $minimum = (float) ReferralSettings::get('referral_friend_minimum_amount', $siteId);

        $attributes = [
            'site_ids' => [$siteId],
            'name' => 'Verwijzing ' . $referralCode->email,
            'type' => $type,
            'discount_percentage' => $type === 'percentage' ? (int) round($value) : null,
            'discount_amount' => $type === 'amount' ? $value : null,
            'use_stock' => false,
            'limit_use_per_customer' => true,
            'minimal_requirements' => $minimum > 0 ? 'amount' : null,
            'minimum_amount' => $minimum > 0 ? $minimum : null,
        ];

        $discountCode = $referralCode->discount_code_id
            ? DiscountCode::withTrashed()->find($referralCode->discount_code_id)
            : null;

        if (! $discountCode) {
            $discountCode = DiscountCode::create(array_merge($attributes, [
                'code' => $referralCode->code,
                'start_date' => now(),
            ]));
            $referralCode->update(['discount_code_id' => $discountCode->id]);

            return $discountCode;
        }

        $discountCode->fill($attributes);

        if ($discountCode->isDirty()) {
            $discountCode->save();
        }

        return $discountCode;
    }

    /**
     * De reden waarom de checkout deze verwijscode weigert, of null. Het
     * e-mailadres is pas in de checkout bekend, daarom gebeurt dit daar en
     * niet bij het invullen van de code.
     */
    public static function checkoutRefusal(?DiscountCode $discountCode, ?string $email, ?int $userId = null): ?string
    {
        $referralCode = self::forDiscountCode($discountCode);

        if (! $referralCode) {
            return null;
        }

        $email = mb_strtolower(trim((string) $email));

        if (! self::enabled($referralCode->site_id)) {
            return Translation::get('referral-discount-inactive', 'referral', 'Deze verwijzingskorting is niet meer geldig');
        }

        if (($email !== '' && $email === $referralCode->email) || ($userId && $userId === (int) $referralCode->user_id)) {
            return Translation::get('referral-discount-own-code', 'referral', 'Je kunt je eigen verwijzingscode niet gebruiken');
        }

        if ($email !== '' && Order::query()->whereIn('status', self::PAID_STATUSES)->where('email', $email)->exists()) {
            return Translation::get('referral-discount-first-order-only', 'referral', 'Deze verwijzingskorting geldt alleen voor je eerste bestelling');
        }

        return null;
    }

    /**
     * Na betaling: legt de verwijzing vast als de bestelling een verwijscode
     * gebruikte. Idempotent op de bestelling.
     */
    public static function recordPaidOrder(Order $order): ?Referral
    {
        if (! $order->discount_code_id || $order->credit_for_order_id) {
            return null;
        }

        $referralCode = ReferralCode::query()->where('discount_code_id', $order->discount_code_id)->first();

        if (! $referralCode) {
            return null;
        }

        $existing = Referral::query()->where('order_id', $order->id)->first();

        if ($existing) {
            return $existing;
        }

        $reason = self::abuseReason($order, $referralCode);
        $waitDays = max(0, (int) ReferralSettings::get('referral_wait_days', $referralCode->site_id));

        $referral = Referral::create([
            'site_id' => $referralCode->site_id,
            'referral_code_id' => $referralCode->id,
            'order_id' => $order->id,
            'referred_email' => mb_strtolower(trim((string) $order->email)),
            'status' => $reason ? Referral::STATUS_REJECTED : Referral::STATUS_PENDING,
            'reject_reason' => $reason,
            'qualifies_at' => now()->addDays($waitDays),
        ]);

        OrderLog::createLog(orderId: $order->id, tag: $reason ? 'order.referral.rejected' : 'order.referral.recorded');

        return $referral;
    }

    protected static function abuseReason(Order $order, ReferralCode $referralCode): ?string
    {
        $email = mb_strtolower(trim((string) $order->email));

        if ($email === $referralCode->email || ($order->user_id && (int) $order->user_id === (int) $referralCode->user_id)) {
            return 'self_referral';
        }

        $earlier = Order::query()
            ->whereIn('status', self::PAID_STATUSES)
            ->where('id', '!=', $order->id)
            ->whereNull('credit_for_order_id')
            ->where(fn (Builder $q) => $q->where('email', $email)
                ->when($order->user_id, fn (Builder $q) => $q->orWhere('user_id', $order->user_id)))
            ->exists();

        if ($earlier) {
            return 'not_first_order';
        }

        $referrerOrders = Order::query()
            ->where('id', '!=', $order->id)
            ->where(fn (Builder $q) => $q->where('email', $referralCode->email)
                ->when($referralCode->user_id, fn (Builder $q) => $q->orWhere('user_id', $referralCode->user_id)));

        $zip = strtoupper(str_replace(' ', '', (string) $order->zip_code));
        $houseNr = strtolower(trim((string) $order->house_nr));

        if ($zip !== '' && $houseNr !== '' && (clone $referrerOrders)
            ->whereRaw("REPLACE(UPPER(zip_code), ' ', '') = ?", [$zip])
            ->whereRaw('LOWER(TRIM(house_nr)) = ?', [$houseNr])
            ->exists()) {
            return 'same_address';
        }

        if ($order->ip && (clone $referrerOrders)->where('ip', $order->ip)->exists()) {
            return 'same_ip';
        }

        return null;
    }

    /**
     * Beloont of wijst af wat de wachttijd voorbij is. Draait dagelijks via
     * dashed:process-referrals.
     *
     * @return array{rewarded: int, rejected: int}
     */
    public static function processDue(): array
    {
        $counts = ['rewarded' => 0, 'rejected' => 0];

        Referral::query()
            ->where('status', Referral::STATUS_PENDING)
            ->where('qualifies_at', '<=', now())
            ->with(['order', 'referralCode'])
            ->orderBy('id')
            ->chunkById(100, function ($referrals) use (&$counts) {
                foreach ($referrals as $referral) {
                    if (self::wasReturned($referral->order)) {
                        self::reject($referral, 'returned');
                        $counts['rejected']++;

                        continue;
                    }

                    if (self::reward($referral)) {
                        $counts['rewarded']++;
                    }
                }
            });

        return $counts;
    }

    public static function wasReturned(?Order $order): bool
    {
        if (! $order || ! in_array($order->status, self::PAID_STATUSES, true)) {
            return true;
        }

        if (Order::query()->where('credit_for_order_id', $order->id)->exists()) {
            return true;
        }

        return OrderReturn::query()
            ->where('order_id', $order->id)
            ->whereNotIn('status', [OrderReturn::STATUS_REJECTED, OrderReturn::STATUS_CLOSED])
            ->exists();
    }

    public static function reject(Referral $referral, string $reason): void
    {
        $referral->update(['status' => Referral::STATUS_REJECTED, 'reject_reason' => $reason]);
    }

    /**
     * Maakt de kortingscode voor de verwijzer en mailt hem. Onder een lock en
     * met een verse lees, zodat een dubbele run nooit twee codes geeft.
     */
    public static function reward(Referral $referral): bool
    {
        return (bool) cache()->lock('referral-reward:' . $referral->id, 30)->get(function () use ($referral) {
            $referral = Referral::with('referralCode')->find($referral->id);

            if (! $referral || $referral->status !== Referral::STATUS_PENDING || ! $referral->referralCode) {
                return false;
            }

            $siteId = $referral->site_id;
            $type = ReferralSettings::get('referral_reward_type', $siteId) === 'percentage' ? 'percentage' : 'amount';
            $value = (float) ReferralSettings::get('referral_reward_value', $siteId);
            $validDays = max(1, (int) ReferralSettings::get('referral_reward_valid_days', $siteId));

            $discountCode = DB::transaction(function () use ($referral, $siteId, $type, $value, $validDays) {
                $discountCode = DiscountCode::create([
                    'site_ids' => [$siteId],
                    'name' => 'Verwijsbeloning ' . $referral->referralCode->email,
                    'code' => self::uniqueCode('BEDANKT'),
                    'type' => $type,
                    'discount_percentage' => $type === 'percentage' ? (int) round($value) : null,
                    'discount_amount' => $type === 'amount' ? $value : null,
                    'use_stock' => true,
                    'stock' => 1,
                    'stock_used' => 0,
                    'limit_use_per_customer' => true,
                    'start_date' => now(),
                    'end_date' => now()->addDays($validDays),
                ]);

                $referral->update([
                    'status' => Referral::STATUS_REWARDED,
                    'reward_discount_code_id' => $discountCode->id,
                    'rewarded_at' => now(),
                ]);

                return $discountCode;
            });

            rescue(fn () => Mail::to($referral->referralCode->email)->send(new ReferralRewardMail($referral->fresh(), $discountCode)));

            return true;
        });
    }

    public static function uniqueCode(string $prefix): string
    {
        do {
            $code = $prefix . '-' . strtoupper(Str::random(6));
        } while (DiscountCode::withTrashed()->where('code', $code)->exists() || ReferralCode::where('code', $code)->exists());

        return $code;
    }
}
