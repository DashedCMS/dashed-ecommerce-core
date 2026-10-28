<?php

namespace Dashed\DashedEcommerceCore\Services\Giftcards;

use Dashed\DashedEcommerceCore\Models\Cart;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedTranslations\Models\Translation;
use Dashed\DashedEcommerceCore\Models\DiscountCode;

/**
 * Verkochte cadeaubonnen op een webshopwagen. Een verkochte bon is
 * betaalmiddel: hij verlaagt het bedrag dat nog betaald moet worden, niet
 * het totaal of de btw. In de wagen staan alleen code en id
 * (`meta['applied_gift_cards']`); saldo en inwisselbedrag worden altijd
 * live uit de bon berekend.
 */
class CartGiftcards
{
    public const META_KEY = 'applied_gift_cards';

    /**
     * @return array{success: bool, message: string}
     */
    public static function apply(Cart $cart, string $code): array
    {
        $code = trim($code);

        if ($code === '') {
            return static::result(false, 'giftcard-no-code', 'Geen code ingevoerd.');
        }

        $giftcard = static::find($code);

        if (! $giftcard || ! $giftcard->isSoldGiftcard()) {
            return static::result(false, 'giftcard-not-found', 'Cadeaubon niet gevonden.');
        }

        if ($giftcard->start_date && $giftcard->start_date->isFuture()) {
            return static::result(false, 'giftcard-not-yet-valid', 'Cadeaubon is nog niet geldig.');
        }

        if ($giftcard->end_date && $giftcard->end_date->isPast()) {
            return static::result(false, 'giftcard-expired', 'Cadeaubon is verlopen.');
        }

        if (round((float) $giftcard->discount_amount, 2) <= 0) {
            return static::result(false, 'giftcard-no-balance', 'Cadeaubon heeft geen saldo meer.');
        }

        $applied = static::applied($cart);

        foreach ($applied as $entry) {
            if ((int) $entry['discount_code_id'] === (int) $giftcard->id) {
                return static::result(false, 'giftcard-already-applied', 'Cadeaubon is al toegepast.');
            }
        }

        if (! static::hasPayableItems($cart)) {
            return static::result(false, 'giftcard-nothing-to-pay', 'Een cadeaubon kan niet gebruikt worden om een cadeaubon te betalen.');
        }

        $applied[] = [
            'code' => $giftcard->code,
            'discount_code_id' => (int) $giftcard->id,
        ];

        static::store($cart, $applied);

        return static::result(true, 'giftcard-applied', 'Cadeaubon toegevoegd.');
    }

    public static function remove(Cart $cart, string $code): void
    {
        $code = mb_strtoupper(trim($code));

        $remaining = array_values(array_filter(
            static::applied($cart),
            fn (array $entry) => mb_strtoupper($entry['code']) !== $code
        ));

        static::store($cart, $remaining);
    }

    /**
     * @return array<int, array{code: string, discount_code_id: int}>
     */
    public static function applied(Cart $cart): array
    {
        $entries = data_get($cart->meta, static::META_KEY, []);

        if (! is_array($entries)) {
            return [];
        }

        $applied = [];

        foreach ($entries as $entry) {
            if (! is_array($entry) || ! isset($entry['code'], $entry['discount_code_id'])) {
                continue;
            }

            $applied[] = [
                'code' => (string) $entry['code'],
                'discount_code_id' => (int) $entry['discount_code_id'],
            ];
        }

        return $applied;
    }

    /**
     * Wat elke toegepaste bon van het betaalbare bedrag afhaalt, in de
     * volgorde waarin ze zijn toegepast: min(saldo, rest). Een bon die niet
     * meer bestaat of geen verkochte bon meer is valt weg; een verlopen bon
     * of een bon zonder saldo blijft zichtbaar met 0.
     *
     * @return array<int, array{code: string, discount_code_id: int, redeemed: float}>
     */
    public static function redemptions(Cart $cart, float $payable): array
    {
        $applied = static::applied($cart);

        if (! $applied) {
            return [];
        }

        $giftcards = DiscountCode::whereIn('id', array_column($applied, 'discount_code_id'))
            ->get()
            ->keyBy('id');

        $rest = max(0.0, round($payable, 2));
        $redemptions = [];

        foreach ($applied as $entry) {
            $giftcard = $giftcards->get($entry['discount_code_id']);

            if (! $giftcard || ! $giftcard->isSoldGiftcard()) {
                continue;
            }

            $redeemed = static::isUsableNow($giftcard)
                ? round(min(max(0.0, (float) $giftcard->discount_amount), $rest), 2)
                : 0.0;

            $rest = round($rest - $redeemed, 2);

            $redemptions[] = [
                'code' => $giftcard->code,
                'discount_code_id' => (int) $giftcard->id,
                'redeemed' => $redeemed,
            ];
        }

        return $redemptions;
    }

    protected static function find(string $code): ?DiscountCode
    {
        return DiscountCode::query()
            ->thisSite()
            ->where('code', $code)
            ->first();
    }

    protected static function isUsableNow(DiscountCode $giftcard): bool
    {
        if ($giftcard->start_date && $giftcard->start_date->isFuture()) {
            return false;
        }

        return ! ($giftcard->end_date && $giftcard->end_date->isPast());
    }

    /**
     * Een cadeaubon betaalt nooit een cadeaubon: er moet minstens één regel
     * in de wagen liggen die geen bonproduct is.
     */
    protected static function hasPayableItems(Cart $cart): bool
    {
        $productIds = $cart->items()->pluck('product_id')->filter()->unique();

        if ($productIds->isEmpty()) {
            return $cart->items()->whereNull('product_id')->exists();
        }

        $products = Product::with('productGroup')->whereIn('id', $productIds)->get();

        if ($products->contains(fn (Product $product) => ! $product->isGiftcardProduct())) {
            return true;
        }

        return $cart->items()->whereNull('product_id')->exists();
    }

    protected static function store(Cart $cart, array $applied): void
    {
        $meta = is_array($cart->meta) ? $cart->meta : [];

        if ($applied) {
            $meta[static::META_KEY] = array_values($applied);
        } else {
            unset($meta[static::META_KEY]);
        }

        $cart->meta = $meta;
        $cart->save();
    }

    /**
     * @return array{success: bool, message: string}
     */
    protected static function result(bool $success, string $key, string $default): array
    {
        return [
            'success' => $success,
            'message' => Translation::get($key, 'cart', $default),
        ];
    }
}
