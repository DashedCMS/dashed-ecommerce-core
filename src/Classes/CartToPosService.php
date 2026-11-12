<?php

namespace Dashed\DashedEcommerceCore\Classes;

use Illuminate\Support\Str;
use Dashed\DashedCore\Models\User;
use Dashed\DashedEcommerceCore\Models\Cart;
use Dashed\DashedEcommerceCore\Models\POSCart;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Models\CartItem;

/**
 * Zet de inhoud van een webshop-winkelwagen over naar de kassa van een
 * medewerker, zodat die de bestelling daar kan afmaken (telefonische klant,
 * klant die vastliep in de checkout). De bronwagen blijft ongemoeid.
 */
class CartToPosService
{
    public static function activePosCart(int $userId): POSCart
    {
        $posCart = POSCart::where('user_id', $userId)->where('status', 'active')->first();

        if (! $posCart) {
            $posCart = new POSCart();
            $posCart->user_id = $userId;
            $posCart->status = 'active';
            $posCart->identifier = uniqid();
            $posCart->country = 'NL';
            $posCart->products = [];
            $posCart->save();
        }

        return $posCart;
    }

    /**
     * Maakt bewust geen kassa-winkelwagen aan: dit draait bij het renderen van
     * de actie.
     */
    public static function activePosCartHasProducts(int $userId): bool
    {
        $posCart = POSCart::where('user_id', $userId)->where('status', 'active')->first();

        return $posCart && count($posCart->products ?? []) > 0;
    }

    public static function copyIntoCart(POSCart $posCart, Cart $cart, bool $copyCustomerDetails = true, bool $copyDiscountCode = true): void
    {
        $cart->loadMissing(['items', 'user', 'discountCode']);

        $notes = [];
        $posCart->products = self::buildCartProducts($cart, $notes);
        $posCart->loaded_concept_order_id = null;
        $posCart->prices_ex_vat = (bool) ($cart->prices_ex_vat ?? false);
        $posCart->shipping_method_id = $cart->shipping_method_id ?: null;
        $posCart->discount_code = null;
        $posCart->applied_discount_codes = [];
        $posCart->applied_gift_cards = [];
        $posCart->note = $notes ? implode("\n", $notes) : null;

        if ($copyCustomerDetails) {
            self::copyCustomerDetailsIntoCart($posCart, $cart);
        }

        $posCart->save();

        if ($copyDiscountCode && $cart->discountCode && ! $cart->discountCode->is_giftcard) {
            // Loopt langs dezelfde controles als wanneer de medewerker de code
            // zelf intypt; een verlopen code valt dus stil weg.
            $posCart->applyDiscountCode((string) $cart->discountCode->code);
        }
    }

    protected static function copyCustomerDetailsIntoCart(POSCart $posCart, Cart $cart): void
    {
        /** @var User|null $customer */
        $customer = $cart->user_id && (int) $cart->user_id !== (int) $posCart->user_id
            ? User::find($cart->user_id)
            : null;

        $posCart->customer_user_id = $customer?->id;
        $posCart->first_name = $customer?->first_name;
        $posCart->last_name = $customer?->last_name;
        $posCart->email = $cart->abandoned_email ?: $customer?->email;

        if ($customer && (bool) ($customer->show_prices_ex_vat ?? false)) {
            $posCart->prices_ex_vat = true;
        }
    }

    /**
     * @param  array<int, string>  $notes  Invulvelden die de kassa niet als extra kent, als regels voor de notitie.
     */
    public static function buildCartProducts(Cart $cart, array &$notes = []): array
    {
        $products = [];

        foreach ($cart->items as $item) {
            /** @var CartItem $item */
            $quantity = max(1, (int) $item->quantity);
            $unitPrice = (float) $item->unit_price;
            $product = $item->product_id && ! data_get($item->options, 'customProduct', false)
                ? Product::find($item->product_id)
                : null;

            if (! $product) {
                $products[] = [
                    'id' => null,
                    'product' => null,
                    'identifier' => (string) Str::random(),
                    'name' => $item->name ?: 'Product',
                    'quantity' => $quantity,
                    'singlePrice' => $unitPrice,
                    'price' => $unitPrice * $quantity,
                    'priceFormatted' => CurrencyHelper::formatPrice($unitPrice * $quantity),
                    'vat_rate' => (float) data_get($item->options, 'vat_rate', 21),
                    'customProduct' => true,
                    'isCustomPrice' => true,
                    'extra' => [],
                    'customId' => 'custom-' . rand(1, 10000000),
                ];

                continue;
            }

            // Een gekozen optie staat in de wagen onder zijn optie-id, een
            // invulveld onder 'product-extra-input-{extraId}'.
            $chosenOptions = [];
            $inputs = [];
            foreach ((array) data_get($item->options, 'options', []) as $key => $option) {
                if (is_numeric($key)) {
                    $chosenOptions[] = ['id' => (int) $key];
                } elseif (str_contains((string) $key, 'product-extra-') && is_array($option)) {
                    $inputs[(string) $key] = $option;
                }
            }

            $name = $item->name ?: $product->getTranslation('name', app()->getLocale());
            $row = [
                'id' => $product->id,
                'identifier' => (string) Str::random(),
                'name' => $name,
                'image' => $product->firstImage ? (mediaHelper()->getSingleMedia($product->firstImage, ['widen' => 300])->url ?? '') : '',
                'quantity' => $quantity,
                'singlePrice' => $unitPrice,
                'price' => $unitPrice * $quantity,
                'priceFormatted' => CurrencyHelper::formatPrice($unitPrice * $quantity),
                'vat_rate' => (float) ($product->vat_rate ?? $product->tax_rate ?? 21),
                'extra' => PosCartExtras::optionMap($chosenOptions),
                'customProduct' => false,
            ];

            if ($inputs) {
                // De kassa kent een invulveld niet als extra. De meerprijs
                // blijft meetellen via 'options' (zelfde sleutels als de
                // webshopwagen) en de tekst gaat mee in de regelnaam en de
                // notitie, zodat hij op de bestelling en de pakbon staat.
                $row['options'] = ['options' => $inputs];

                $texts = [];
                foreach ($inputs as $input) {
                    $value = trim((string) (is_scalar($input['value'] ?? null) ? $input['value'] : ''));
                    if ($value !== '') {
                        $texts[] = trim((string) ($input['name'] ?? '')) . ': ' . $value;
                    }
                }

                if ($texts) {
                    $row['name'] = $name . ' (' . implode(', ', $texts) . ')';
                    $notes[] = $name . ' - ' . implode(', ', $texts);
                }
            }

            $products[] = $row;
        }

        return $products;
    }
}
