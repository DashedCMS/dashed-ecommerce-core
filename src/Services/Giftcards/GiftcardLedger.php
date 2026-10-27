<?php

namespace Dashed\DashedEcommerceCore\Services\Giftcards;

use Illuminate\Support\Facades\DB;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\DiscountCode;

/**
 * Het saldo van verkochte cadeaubonnen die een webshoporder (deels) betalen.
 *
 * Op de order staat per bon een regel in `applied_gift_cards`:
 * {code, discount_code_id, redeemed, status, refunded}. De status loopt van
 * `reserved` (order aangemaakt, saldo vast) naar `used` (order betaald) of
 * `released` (order vervallen, saldo terug op de bon). Op de bon zelf houden
 * discount_amount (saldo), reserved_amount en used_amount dezelfde stappen bij.
 *
 * Regels zonder `status` komen van de kassa, die het saldo zelf afboekt; die
 * blijven hier altijd ongemoeid.
 *
 * Elke stap vergrendelt de order en de bon, zodat twee bestellingen tegelijk
 * nooit samen meer dan het saldo uitgeven en een dubbele aanroep (betaald via
 * de exchange én de klantpagina) niets dubbel boekt.
 */
class GiftcardLedger
{
    public const STATUS_RESERVED = 'reserved';

    public const STATUS_USED = 'used';

    public const STATUS_RELEASED = 'released';

    public const STATUS_TRANSFERRED = 'transferred';

    /**
     * @param  array<int, array{code?: string, discount_code_id: int, redeemed: float}>  $redemptions
     * @return array<int, array{code: string, discount_code_id: int, redeemed: float, status: string, refunded: float}>
     */
    public static function reserve(Order $order, array $redemptions): array
    {
        return DB::transaction(function () use ($order, $redemptions) {
            $lockedOrder = static::lockOrder($order);
            $entries = static::entries($lockedOrder);
            $reserved = [];

            foreach ($redemptions as $redemption) {
                $wanted = round(max(0.0, (float) ($redemption['redeemed'] ?? 0)), 2);

                if ($wanted <= 0) {
                    continue;
                }

                $card = DiscountCode::whereKey((int) ($redemption['discount_code_id'] ?? 0))->lockForUpdate()->first();

                if (! $card || ! $card->isSoldGiftcard() || ! static::isUsableNow($card)) {
                    continue;
                }

                $balance = round(max(0.0, (float) $card->discount_amount), 2);
                $redeemed = round(min($wanted, $balance), 2);

                if ($redeemed <= 0) {
                    continue;
                }

                $newBalance = round($balance - $redeemed, 2);

                $card->forceFill([
                    'discount_amount' => $newBalance,
                    'reserved_amount' => round((float) $card->reserved_amount + $redeemed, 2),
                ])->save();

                $card->createLog(tag: 'giftcard.payment.reserved', orderId: $order->id, oldAmount: $balance, newAmount: $newBalance);

                $entry = [
                    'code' => (string) $card->code,
                    'discount_code_id' => (int) $card->id,
                    'redeemed' => $redeemed,
                    'status' => static::STATUS_RESERVED,
                    'refunded' => 0.0,
                ];

                $entries[] = $entry;
                $reserved[] = $entry;
            }

            if ($reserved) {
                static::store($order, $lockedOrder, $entries);
            }

            return $reserved;
        });
    }

    /**
     * Order betaald: de reservering wordt verbruik. Het saldo verandert niet.
     */
    public static function commit(Order $order): void
    {
        static::transition($order, function (DiscountCode $card, array $entry) use ($order) {
            $balance = round((float) $card->discount_amount, 2);

            $card->forceFill([
                'reserved_amount' => round(max(0.0, (float) $card->reserved_amount - $entry['redeemed']), 2),
                'used_amount' => round((float) $card->used_amount + $entry['redeemed'], 2),
            ])->save();

            $card->createLog(tag: 'giftcard.payment.completed', orderId: $order->id, oldAmount: $balance, newAmount: $balance);

            return static::STATUS_USED;
        });
    }

    /**
     * Order vervallen zonder betaling: het gereserveerde bedrag gaat terug op
     * de bon. Een bon die al verbruikt is (order betaald) blijft verbruikt;
     * dat gaat via refund().
     */
    public static function release(Order $order): void
    {
        $released = static::transition($order, function (DiscountCode $card, array $entry) use ($order) {
            $balance = round((float) $card->discount_amount, 2);
            $newBalance = round($balance + $entry['redeemed'], 2);

            $card->forceFill([
                'discount_amount' => $newBalance,
                'reserved_amount' => round(max(0.0, (float) $card->reserved_amount - $entry['redeemed']), 2),
            ])->save();

            $card->createLog(tag: 'giftcard.payment.released', orderId: $order->id, oldAmount: $balance, newAmount: $newBalance);

            return static::STATUS_RELEASED;
        });

        // De bonbetalingen tellen niet meer mee voor wat er op de order
        // betaald is: het geld staat weer op de bon.
        if ($released) {
            $order->orderPayments()
                ->where('psp', 'giftcard')
                ->where('status', 'paid')
                ->update(['status' => 'cancelled']);
        }
    }

    /**
     * Order vervangen door een wijziging (OrderModificationService): de
     * vervanger erfde dezelfde regels en de bonbetalingen. Op de oude order
     * gaat de reservering op `transferred`, zodat hij nooit meer vrijgegeven
     * of afgeboekt wordt. Het saldo op de bon verandert niet.
     */
    public static function transfer(Order $order): void
    {
        static::transition($order, fn () => static::STATUS_TRANSFERRED);
    }

    /**
     * Zet bij een (deel)retour tot $amount terug op de bonnen waarmee de order
     * betaald is, de laatst gebruikte bon eerst, en nooit meer dan er per bon
     * van betaald is. Geeft terug wat er werkelijk teruggezet is; de rest
     * hoort via de betaalprovider terug.
     */
    public static function refund(Order $order, float $amount): float
    {
        $amount = round(max(0.0, $amount), 2);

        if ($amount <= 0) {
            return 0.0;
        }

        return DB::transaction(function () use ($order, $amount) {
            $lockedOrder = static::lockOrder($order);
            $entries = static::entries($lockedOrder);
            $left = $amount;
            $changed = false;

            for ($i = count($entries) - 1; $i >= 0 && $left > 0; $i--) {
                $entry = $entries[$i];

                if (($entry['status'] ?? null) !== static::STATUS_USED) {
                    continue;
                }

                $refundable = round((float) $entry['redeemed'] - (float) ($entry['refunded'] ?? 0), 2);
                $back = round(min($left, $refundable), 2);

                if ($back <= 0) {
                    continue;
                }

                $card = DiscountCode::whereKey((int) $entry['discount_code_id'])->lockForUpdate()->first();

                if (! $card) {
                    continue;
                }

                $balance = round((float) $card->discount_amount, 2);
                $newBalance = round($balance + $back, 2);

                $card->forceFill([
                    'discount_amount' => $newBalance,
                    'used_amount' => round(max(0.0, (float) $card->used_amount - $back), 2),
                ])->save();

                $card->createLog(tag: 'giftcard.payment.refunded', orderId: $order->id, oldAmount: $balance, newAmount: $newBalance);

                $entries[$i]['refunded'] = round((float) ($entry['refunded'] ?? 0) + $back, 2);
                $left = round($left - $back, 2);
                $changed = true;
            }

            if ($changed) {
                static::store($order, $lockedOrder, $entries);
            }

            return round($amount - $left, 2);
        });
    }

    /**
     * Zet elke regel met status `reserved` om via $apply (die de bon bijwerkt
     * en de nieuwe status teruggeeft). Geeft terug of er iets veranderde.
     */
    protected static function transition(Order $order, callable $apply): bool
    {
        // Snelle uitweg voor de gewone order zonder bonnen: geen transactie
        // en geen lock bij elke statuswijziging.
        if (! collect(static::entries($order))->contains(fn ($entry) => ($entry['status'] ?? null) === static::STATUS_RESERVED)) {
            return false;
        }

        return DB::transaction(function () use ($order, $apply) {
            $lockedOrder = static::lockOrder($order);
            $entries = static::entries($lockedOrder);
            $changed = false;

            foreach ($entries as $i => $entry) {
                if (($entry['status'] ?? null) !== static::STATUS_RESERVED) {
                    continue;
                }

                $card = DiscountCode::whereKey((int) ($entry['discount_code_id'] ?? 0))->lockForUpdate()->first();

                if (! $card) {
                    continue;
                }

                $entries[$i]['status'] = $apply($card, [
                    'redeemed' => round((float) ($entry['redeemed'] ?? 0), 2),
                ]);
                $changed = true;
            }

            if ($changed) {
                static::store($order, $lockedOrder, $entries);
            }

            return $changed;
        });
    }

    protected static function lockOrder(Order $order): Order
    {
        return Order::whereKey($order->id)->lockForUpdate()->first() ?? $order;
    }

    protected static function entries(Order $order): array
    {
        $entries = $order->applied_gift_cards;

        return is_array($entries) ? array_values($entries) : [];
    }

    /**
     * Rechtstreeks opslaan (saveQuietly): dit is boekhouding naast de order,
     * geen wijziging waar order-observers op moeten reageren.
     */
    protected static function store(Order $order, Order $lockedOrder, array $entries): void
    {
        $lockedOrder->applied_gift_cards = array_values($entries);
        $lockedOrder->saveQuietly();

        $order->applied_gift_cards = $lockedOrder->applied_gift_cards;
        $order->syncOriginalAttribute('applied_gift_cards');
    }

    protected static function isUsableNow(DiscountCode $card): bool
    {
        if ($card->start_date && $card->start_date->isFuture()) {
            return false;
        }

        return ! ($card->end_date && $card->end_date->isPast());
    }
}
