<?php

namespace Dashed\DashedEcommerceCore\Classes;

use Exception;
use Illuminate\Support\Str;
use Dashed\DashedPages\Models\Page;
use Dashed\DashedCore\Classes\Sites;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Filament\Notifications\Notification;
use Gloudemans\Shoppingcart\Facades\Cart;
use Dashed\DashedCore\Models\Customsetting;
use Illuminate\Database\Eloquent\Collection;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedTranslations\Models\Translation;
use Dashed\DashedEcommerceCore\Models\DiscountCode;
use Dashed\DashedEcommerceCore\Models\ShippingZone;
use Dashed\DashedEcommerceCore\Models\PaymentMethod;
use Dashed\DashedEcommerceCore\Models\ShippingMethod;

class ShoppingCart
{
    public static function getApplyDiscountCodeUrl()
    {
        return url(route('dashed.frontend.cart.apply-discount-code'));
    }

    public static function getAddToCartUrl(Product $product)
    {
        return url(route('dashed.frontend.cart.add-to-cart', ['product' => $product]));
    }

    public static function getRemoveFromCartUrl($item)
    {
        return url(route('dashed.frontend.cart.remove-from-cart', ['rowId' => $item->rowId]));
    }

    public static function getUpdateToCartUrl($item)
    {
        return url(route('dashed.frontend.cart.update-to-cart', ['rowId' => $item->rowId]));
    }

    public static function getCartUrl()
    {
        $pageId = Customsetting::get('cart_page_id');
        $page = Page::publicShowable()->where('id', $pageId)->first();

        return $page->getUrl() ?? '#';
    }

    public static function getCheckoutUrl()
    {
        $pageId = Customsetting::get('checkout_page_id');
        $page = Page::publicShowable()->where('id', $pageId)->first();

        return $page->getUrl() ?? '#';
    }

    public static function getCompleteUrl()
    {
        $pageId = Customsetting::get('order_page_id');
        $page = Page::publicShowable()->where('id', $pageId)->first();

        return $page->getUrl(native: false) ?? '#';
    }

    public static function getStartTransactionUrl()
    {
        return url(route('dashed.frontend.start-transaction'));
    }

    public static function cartItems(?string $cartType = null)
    {
        if ($cartType) {
            self::setInstance($cartType);
        }

        return Cart::content();
    }

    public static function cartItemsCount()
    {
        return Cart::count();
    }

    public static function setInstance(string $cartType = 'default')
    {
        Cart::instance($cartType);
    }

    public static function totalDiscount($formatResult = false, ?string $discountCodeToUse = null)
    {
        $totalDiscount = 0;

        //        ray('session ' . session('discountCode'));
        $discountCode = $discountCodeToUse ?: session('discountCode');
        //        ray($discountCode);
        if ($discountCode) {
            $discountCode = DiscountCode::usable()->where('code', $discountCode)->first();

            if (! $discountCode || ! $discountCode->isValidForCart()) {
                session(['discountCode' => '']);
            } else {
                if ($discountCode->type == 'percentage') {
                    $itemsInCart = self::cartItems();

                    foreach ($itemsInCart as $item) {
                        //                        $discountedPrice = $discountCode->getDiscountedPriceForProduct($item->model, $item->qty);
                        $totalDiscount += Product::getShoppingCartItemPrice($item) - Product::getShoppingCartItemPrice($item, $discountCode);
                    }
                } elseif ($discountCode->type == 'amount') {
                    $totalDiscount = $discountCode->discount_amount;
                }
            }
        }

        if ($totalDiscount) {
            if ($formatResult) {
                return CurrencyHelper::formatPrice($totalDiscount);
            } else {
                return number_format($totalDiscount, 2, '.', '');
            }
        } else {
            return 0;
        }
    }

    public static function subtotal($formatResult = false, $shippingMethodId = null, $paymentMethodId = null, $total = null)
    {
        $cartTotal = $total ?: self::total(false, false, $shippingMethodId, $paymentMethodId);

        $calculateInclusiveTax = Customsetting::get('taxes_prices_include_taxes');
        if (! $calculateInclusiveTax) {
            //            dd($cartTotal, self::btw(false, false, $shippingMethodId, $paymentMethodId));
            $cartTotal -= self::btw(false, false, $shippingMethodId, $paymentMethodId);

            if ($shippingMethodId) {
                $shippingMethod = ShippingMethod::find($shippingMethodId);
                if ($shippingMethod) {
                    $cartTotal -= $shippingMethod->costsForCart;
                }
            }

            if ($paymentMethodId) {
                foreach (ShoppingCart::getPaymentMethods() as $paymentMethod) {
                    if ($paymentMethod['id'] == $paymentMethodId) {
                        $cartTotal -= $paymentMethod['extra_costs'];
                    }
                }
            }
        }

        if ($formatResult) {
            return CurrencyHelper::formatPrice($cartTotal);
        } else {
            return number_format($cartTotal, 2, '.', '');
        }
    }

    public static function total($formatResult = false, $calculateDiscount = true, $shippingMethodId = null, $paymentMethodId = null, $tax = null, $discount = null)
    {
        $cartTotal = 0;
        foreach (self::cartItems() as $cartItem) {
            $cartTotal += $cartItem->model ? Product::getShoppingCartItemPrice($cartItem) : ($cartItem->price * $cartItem->qty);
            //            $cartTotal = $cartTotal + ($cartItem->model->currentPrice * $cartItem->qty);
        }

        if ($calculateDiscount) {
            $cartTotal = $cartTotal - ($discount ?: self::totalDiscount());
        }

        $calculateInclusiveTax = Customsetting::get('taxes_prices_include_taxes');
        if (! $calculateInclusiveTax) {
            $tax = $tax ?: self::btw(false, $calculateDiscount, $shippingMethodId, $paymentMethodId);
            $cartTotal = $cartTotal + $tax;
        }

        if ($shippingMethodId) {
            $shippingMethod = ShippingMethod::find($shippingMethodId);
            if ($shippingMethod) {
                //                dump($cartTotal);
                $cartTotal += $shippingMethod->costsForCart;
                //                dd($cartTotal);
            }
        }

        if ($paymentMethodId) {
            foreach (ShoppingCart::getPaymentMethods() as $paymentMethod) {
                if ($paymentMethod['id'] == $paymentMethodId) {
                    $cartTotal += $paymentMethod['extra_costs'];
                }
            }
        }

        if ($formatResult) {
            return CurrencyHelper::formatPrice($cartTotal);
        } else {
            return number_format($cartTotal, 2, '.', '');
        }
    }

    public static function depositAmount($formatResult = false, $calculateDiscount = true, $shippingMethodId = null, $paymentMethodId = null, $total = null)
    {
        $depositAmount = 0;

        if ($paymentMethodId) {
            foreach (ShoppingCart::getPaymentMethods() as $paymentMethod) {
                if ($paymentMethod['id'] == $paymentMethodId) {
                    if ($paymentMethod['deposit_calculation']) {
                        $paymentMethod['deposit_calculation'] = str_replace('{ORDER_TOTAL_MINUS_PAYMENT_COSTS}', $total ?: self::total(false, $calculateDiscount, $shippingMethodId, null), $paymentMethod['deposit_calculation']);
                        $paymentMethod['deposit_calculation'] = str_replace('{ORDER_TOTAL}', $total ?: self::total(false, $calculateDiscount, $shippingMethodId, $paymentMethodId), $paymentMethod['deposit_calculation']);
                        $depositAmount = eval('return ' . $paymentMethod['deposit_calculation'] . ';');
                    }
                }
            }
        }

        if ($formatResult) {
            return CurrencyHelper::formatPrice($depositAmount);
        } else {
            return number_format($depositAmount, 2, '.', '');
        }
    }

    public static function amounts($formatResult = false, $calculateDiscount = true, $shippingMethodId = null, $paymentMethodId = null)
    {
        $discount = self::totalDiscount(false);
        $tax = self::btw(false, $calculateDiscount, $shippingMethodId, $paymentMethodId);
        $total = self::total(false, false, $shippingMethodId, $paymentMethodId, $tax, $discount);
        $subTotal = self::subtotal(false, $shippingMethodId, $paymentMethodId, $total);

        if ($formatResult) {
            return [
                'subTotal' => CurrencyHelper::formatPrice($subTotal),
                'discount' => CurrencyHelper::formatPrice($discount),
                'tax' => CurrencyHelper::formatPrice($tax),
                'total' => CurrencyHelper::formatPrice($total),
//                'shippingCosts' => CurrencyHelper::formatPrice($shippingCosts),
//                'paymentCosts' => CurrencyHelper::formatPrice($paymentCosts),
//                'depositAmount' => CurrencyHelper::formatPrice($depositAmount),
            ];
        } else {
            return [
                'subTotal' => number_format($subTotal, 2, '.', ''),
                'discount' => number_format($discount, 2, '.', ''),
                'tax' => number_format($tax, 2, '.', ''),
                'total' => number_format($total, 2, '.', ''),
//                'shippingCosts' => number_format($shippingCosts, 2, '.', ''),
//                'paymentCosts' => number_format($paymentCosts, 2, '.', ''),
//                'depositAmount' => number_format($depositAmount, 2, '.', ''),
            ];
        }
    }

    public static function btw($formatResult = false, $calculateDiscount = true, $shippingMethodId = null, $paymentMethodId = null)
    {
        $calculateInclusiveTax = Customsetting::get('taxes_prices_include_taxes');
        $baseVatInfo = self::getVatBaseInfoForCalculation($calculateDiscount);
        $discountCode = $baseVatInfo['discountCode'];

        $taxTotal = $baseVatInfo['taxTotal'];

        if ($discountCode && $discountCode->type == 'amount') {
            if ($calculateInclusiveTax) {
                foreach ($baseVatInfo['vatPercentageOfTotals'] as $percentage => $vatPercentageOfTotal) {
                    if ($vatPercentageOfTotal) {
                        $taxTotal -= (($discountCode->discount_amount * ($vatPercentageOfTotal / 100)) / (100 + $percentage) * $percentage);
                    }
                }
            } else {
                foreach ($baseVatInfo['vatPercentageOfTotals'] as $percentage => $vatPercentageOfTotal) {
                    if ($vatPercentageOfTotal) {
                        $taxTotal -= (($discountCode->discount_amount * ($vatPercentageOfTotal / 100)) / 100 * $percentage);
                    }
                }
            }
        }

        if ($shippingMethodId) {
            $taxTotal += self::vatForShippingMethod($shippingMethodId, false, $calculateDiscount);
        }

        if ($paymentMethodId) {
            $taxTotal += self::vatForPaymentMethod($paymentMethodId);
        }

        if ($formatResult) {
            return CurrencyHelper::formatPrice($taxTotal);
        } else {
            return number_format($taxTotal, 2, '.', '');
        }
    }

    public static function btwPercentages($formatResult = false, $calculateDiscount = true, $shippingMethodId = null, $paymentMethodId = null): array
    {
        $calculateInclusiveTax = Customsetting::get('taxes_prices_include_taxes');
        $baseVatInfo = self::getVatBaseInfoForCalculation($calculateDiscount);
        $discountCode = $baseVatInfo['discountCode'];

        $totalVatPerPercentage = $baseVatInfo['totalVatPerPercentage'];

        if ($discountCode && $discountCode->type == 'amount') {
            if ($calculateInclusiveTax) {
                foreach ($baseVatInfo['vatPercentageOfTotals'] as $percentage => $vatPercentageOfTotal) {
                    if ($vatPercentageOfTotal) {
                        foreach ($totalVatPerPercentage as $percentage => $value) {
                            $totalVatPerPercentage[$percentage] -= (($discountCode->discount_amount * ($vatPercentageOfTotal / 100)) / (100 + $percentage) * $percentage);
                        }
                    }
                }
            } else {
                foreach ($baseVatInfo['vatPercentageOfTotals'] as $percentage => $vatPercentageOfTotal) {
                    if ($vatPercentageOfTotal) {
                        foreach ($totalVatPerPercentage as $percentage => $value) {
                            $totalVatPerPercentage[$percentage] -= (($discountCode->discount_amount * ($vatPercentageOfTotal / 100)) / 100 * $percentage);
                        }
                    }
                }
            }
        }

        if ($shippingMethodId) {
            foreach ($totalVatPerPercentage as $percentage => $value) {
                $result = self::vatForShippingMethod($shippingMethodId, false, $calculateDiscount, $percentage);
                //                $result = self::vatForShippingMethod($shippingMethodId, false, $calculateDiscount, $percentage) / 100 * $baseVatInfo['vatPercentageOfTotals'][$percentage];
                $totalVatPerPercentage[$percentage] += $result;
            }
        }

        if ($paymentMethodId) {
            $paymentVat = self::vatForPaymentMethod($paymentMethodId);
            isset($totalVatPerPercentage[21]) ? $totalVatPerPercentage[21] += $paymentVat : $totalVatPerPercentage[21] = $paymentVat;
        }

        foreach ($totalVatPerPercentage as $percentage => $value) {
            $totalVatPerPercentage[$percentage] = round($value, 2);
        }

        return $totalVatPerPercentage;
    }

    public static function vatForShippingMethod($shippingMethodId, $formatResult = false, $calculateDiscount = true, $vatRate = null)
    {
        $calculateInclusiveTax = Customsetting::get('taxes_prices_include_taxes');

        if (! $vatRate) {
            $vatRate = self::vatRateForShippingMethod($shippingMethodId);
        }

        $taxTotal = 0;

        $shippingMethod = ShippingMethod::find($shippingMethodId);
        if ($shippingMethod) {
            if ($calculateInclusiveTax) {
                $taxTotal += $shippingMethod->costsForCart / (100 + $vatRate) * $vatRate;
            } else {
                $taxTotal += $shippingMethod->costsForCart / 100 * $vatRate;
            }
        }

        return round($taxTotal, 2);
    }

    public static function vatRateForShippingMethod($shippingMethodId)
    {
        $baseVatInfo = self::getVatBaseInfoForCalculation();

        $shippingMethod = ShippingMethod::find($shippingMethodId);
        if ($shippingMethod && $baseVatInfo['vatRatesCount']) {
            return round($baseVatInfo['vatRates'] / $baseVatInfo['vatRatesCount'], 2);
        }

        return 0;
    }

    public static function vatForPaymentMethod($paymentMethodId)
    {
        $calculateInclusiveTax = Customsetting::get('taxes_prices_include_taxes');

        $taxTotal = 0;

        foreach (ShoppingCart::getPaymentMethods() as $paymentMethod) {
            if ($paymentMethod['id'] == $paymentMethodId) {
                if ($calculateInclusiveTax) {
                    $taxTotal = $taxTotal + ($paymentMethod['extra_costs'] / 121 * 21);
                } else {
                    $taxTotal = $taxTotal + ($paymentMethod['extra_costs'] / 100 * 21);
                }
            }
        }

        return $taxTotal;
    }

    public static function getVatBaseInfoForCalculation($calculateDiscount = true): array
    {
        $calculateInclusiveTax = Customsetting::get('taxes_prices_include_taxes');
        $taxTotal = 0;
        $vatRates = 0;
        $vatRatesCount = 0;

        if ($calculateDiscount) {
            $discountCode = DiscountCode::usable()->where('code', session('discountCode'))->first();

            if (! $discountCode || ! $discountCode->isValidForCart()) {
                session(['discountCode' => '']);
                $discountCode = null;
            }
        } else {
            $discountCode = null;
        }

        $totalAmountForVats = [];

        $totalPriceForProducts = 0;

        foreach (self::cartItems() as $cartItem) {
            if ($cartItem->model || ($cartItem->options['customProduct'] ?? false)) {
                //                $isBundleItemWithIndividualPricing = false;
                $cartProducts = [$cartItem->model ?? $cartItem];
                if ($cartItem->model && $cartItem->model->is_bundle && $cartItem->model->use_bundle_product_price) {
                    //                    $isBundleItemWithIndividualPricing = true;
                    $cartProducts = $cartItem->model->bundleProducts;
                }

                foreach ($cartProducts as $cartProduct) {
                    if ($discountCode && $discountCode->type == 'percentage') {
                        $price = Product::getShoppingCartItemPrice($cartItem, $discountCode);
                    } else {
                        $price = Product::getShoppingCartItemPrice($cartItem);
                    }
                    //                    dump($price);
                    $totalPriceForProducts += $price;

                    //                    dump($price);
                    if ($calculateInclusiveTax) {
                        $price = $price / (100 + ($cartProduct->options['vat_rate'] ?? $cartProduct->vat_rate)) * ($cartProduct->options['vat_rate'] ?? $cartProduct->vat_rate);
                    } else {
                        $price = $price / 100 * ($cartProduct->options['vat_rate'] ?? $cartProduct->vat_rate);
                    }
                    //                    dump($price);

                    $taxTotal += $price;
                    if (($cartProduct->options['vat_rate'] ?? $cartProduct->vat_rate) > 0) {
                        //                        dump($cartProduct->options['vat_rate'] ?? $cartProduct->vat_rate);
                        if (! isset($totalAmountForVats[number_format(($cartProduct->options['vat_rate'] ?? $cartProduct->vat_rate), 0)])) {
                            $totalAmountForVats[number_format(($cartProduct->options['vat_rate'] ?? $cartProduct->vat_rate), 0)] = 0;
                        }
                        if ($discountCode && $discountCode->type == 'percentage') {
                            $totalCartItemAmount = Product::getShoppingCartItemPrice($cartItem, $discountCode);
                            if (! $calculateInclusiveTax) {
                                $totalCartItemAmount = $totalCartItemAmount / 100 * (100 + ($cartProduct->options['vat_rate'] ?? $cartProduct->vat_rate));
                            }

                            $totalAmountForVats[number_format(($cartProduct->options['vat_rate'] ?? $cartProduct->vat_rate), 0)] += $totalCartItemAmount;
                        } else {
                            $totalCartItemAmount = Product::getShoppingCartItemPrice($cartItem);
                            if (! $calculateInclusiveTax) {
                                $totalCartItemAmount = $totalCartItemAmount / 100 * (100 + ($cartProduct->options['vat_rate'] ?? $cartProduct->vat_rate));
                            }

                            $totalAmountForVats[number_format(($cartProduct->options['vat_rate'] ?? $cartProduct->vat_rate), 0)] += $totalCartItemAmount;
                        }
                        //                        $totalAmountForVats[($cartProduct->options['vat_rate'] ?? $cartProduct->vat_rate)] += (($isBundleItemWithIndividualPricing ? $cartProduct->currentPrice : $cartItem->model->currentPrice) * $cartItem->qty);
                        $vatRates += ($cartProduct->options['vat_rate'] ?? $cartProduct->vat_rate) * $cartItem->qty;
                        $vatRatesCount += $cartItem->qty;
                    }
                }
            } else {
                //                dd($cartItem);
            }
        }

        $vatPercentageOfTotals = [];
        $totalVatPerPercentage = [];

        foreach ($totalAmountForVats as $percentage => $totalAmountForVat) {
            if (! isset($vatPercentageOfTotals[number_format($percentage, 0)])) {
                $vatPercentageOfTotals[number_format($percentage, 0)] = 0;
            }
            if (! isset($totalVatPerPercentage[number_format($percentage, 0)])) {
                $totalVatPerPercentage[number_format($percentage, 0)] = 0;
            }
            $vatPercentageOfTotals[number_format($percentage, 0)] += $totalAmountForVat > 0.00 && $totalPriceForProducts > 0.00 ? ($totalAmountForVat / $totalPriceForProducts) * 100 : 0;
            $totalVatPerPercentage[number_format($percentage, 0)] += $totalAmountForVat > 0.00 ? ($totalAmountForVat / (100 + $percentage) * $percentage) : 0;
        }

        return [
            'totalAmountForVats' => $totalAmountForVats,
            'totalVatPerPercentage' => $totalVatPerPercentage,
            'vatPercentageOfTotals' => $vatPercentageOfTotals,
            'vatRates' => $vatRates,
            'vatRatesCount' => $vatRatesCount,
            'taxTotal' => $taxTotal,
            'discountCode' => $discountCode,
        ];
    }

    public static function getAvailableShippingMethods($countryName, $formatResult = false, string $shippingAddress = '')
    {
        $shippingZones = ShippingZone::get();
        foreach ($shippingZones as $shippingZone) {
            $shippingZoneIsActive = false;
            foreach ($shippingZone->zones as $zone) {
                foreach (Countries::getCountries() as $country) {
                    if ($country['name'] == $zone) {
                        if (strtolower($country['name']) == strtolower($countryName)) {
                            $shippingZoneIsActive = true;
                        }
                        if (strtolower($country['alpha2Code']) == strtolower($countryName)) {
                            $shippingZoneIsActive = true;
                        }
                        if (strtolower($country['alpha3Code']) == strtolower($countryName)) {
                            $shippingZoneIsActive = true;
                        }
                        if (strtolower($country['demonym']) == strtolower($countryName)) {
                            $shippingZoneIsActive = true;
                        }
                        foreach ($country['altSpellings'] as $altSpelling) {
                            if (strlen($countryName) > 5) {
                                if (Str::contains(strtolower($altSpelling), strtolower($countryName))) {
                                    $shippingZoneIsActive = true;
                                }
                            } else {
                                if (strtolower($altSpelling) == strtolower($countryName)) {
                                    $shippingZoneIsActive = true;
                                }
                            }
                        }
                    }
                }
            }

            if (! $shippingZoneIsActive && $shippingZone->search_fields) {
                $searchFields = explode(',', $shippingZone->search_fields);
                foreach ($searchFields as $searchField) {
                    $searchField = trim($searchField);
                    if (strtolower($searchField) == strtolower($countryName)) {
                        $shippingZoneIsActive = true;
                    }
                }
            }

            if ($shippingZoneIsActive) {
                $distanceRange = 10000;
                $fromAddress = Customsetting::get('company_street') . ' ' . Customsetting::get('company_street_number') . ', ' . Customsetting::get('company_postal_code') . ' ' . Customsetting::get('company_city') . ', ' . Customsetting::get('company_country');
                if ($shippingAddress && $fromAddress && Customsetting::get('checkout_google_api_key')) {
                    $distanceResponse = Http::get("https://maps.googleapis.com/maps/api/distancematrix/json?destinations=$shippingAddress&origins=$fromAddress&units=imperial&key=" . Customsetting::get('checkout_google_api_key'))
                        ->json();
                    if ($distanceResponse['status'] == 'OK') {
                        $distanceRange = ($distanceResponse['rows'][0]['elements'][0]['distance']['value'] ?? 10000000) / 1000;
                    }
                }

                $total = self::total();
                $shippingMethods = $shippingZone->shippingMethods()
                    ->where('minimum_order_value', '<=', $total)
                    ->where('maximum_order_value', '>=', $total)
//                    ->where(function ($query) use ($distanceRange) {
//                        $query->where('distance_range_enabled', 1)
//                            ->where('distance_range', '>=', $distanceRange);
//                    })
//                    ->orWhere('distance_range_enabled', 0)
                    ->orderBy('order', 'ASC')
                    ->get();

                foreach ($shippingMethods as $key => $shippingMethod) {
                    $shippingMethodValid = true;
                    if ($shippingMethod->distance_range_enabled && $distanceRange > $shippingMethod->distance_range) {
                        $shippingMethodValid = false;
                    }

                    if ($shippingMethodValid) {
                        $shippingMethod->correctName = $shippingMethod->getTranslation('name', app()->getLocale());
                        $costs = $shippingMethod->costsForCart;
                        $cartItems = self::cartItems();
                        foreach ($shippingMethod->shippingMethodClasses as $shippingClass) {
                            foreach ($cartItems as $cartItem) {
                                if ($cartItem->model->shippingClasses->contains($shippingClass->id)) {
                                    $costs = $costs + ($shippingClass->costs * $cartItem->qty);
                                }
                            }
                        }
                        $shippingMethod->costs = $costs;
                        if ($shippingMethod->costs == 0) {
                            $shippingMethod->costsFormatted = Translation::get('free', 'checkout', 'Gratis');
                        } else {
                            $shippingMethod->costsFormatted = CurrencyHelper::formatPrice($costs);
                        }
                    } else {
                        unset($shippingMethods[$key]);
                    }
                }

                return $shippingMethods;
            }
        }

        return [];
    }

    public static function getAvailablePaymentMethods($countryName, $formatResult = false)
    {
        $paymentMethods = self::getPaymentMethods();
        $shippingZones = ShippingZone::get();
        foreach ($shippingZones as $shippingZone) {
            $shippingZoneIsActive = false;

            foreach ($shippingZone->zones as $zone) {
                foreach (Countries::getCountries() as $country) {
                    if ($country['name'] == $zone) {
                        if (strtolower($country['name']) == strtolower($countryName)) {
                            $shippingZoneIsActive = true;
                        }
                        if (strtolower($country['alpha2Code']) == strtolower($countryName)) {
                            $shippingZoneIsActive = true;
                        }
                        if (strtolower($country['alpha3Code']) == strtolower($countryName)) {
                            $shippingZoneIsActive = true;
                        }
                        if (strtolower($country['demonym']) == strtolower($countryName)) {
                            $shippingZoneIsActive = true;
                        }
                        foreach ($country['altSpellings'] as $altSpelling) {
                            if (strlen($countryName) > 5) {
                                if (Str::contains(strtolower($altSpelling), strtolower($countryName))) {
                                    $shippingZoneIsActive = true;
                                }
                            } else {
                                if (strtolower($altSpelling) == strtolower($countryName)) {
                                    $shippingZoneIsActive = true;
                                }
                            }
                        }
                    }
                }
            }

            if (! $shippingZoneIsActive && $shippingZone->search_fields) {
                $searchFields = explode(',', $shippingZone->search_fields);
                foreach ($searchFields as $searchField) {
                    if (strtolower($searchField) == strtolower($countryName)) {
                        $shippingZoneIsActive = true;
                    }
                }
            }

            if ($shippingZoneIsActive && $shippingZone->disabled_payment_method_ids) {
                $correctPaymentMethods = [];
                foreach ($paymentMethods as $key => $paymentMethod) {
                    $isCorrectPaymentMethod = true;
                    foreach ($shippingZone->disabled_payment_method_ids as $disabledPaymentMethodId) {
                        if ($disabledPaymentMethodId == $paymentMethod['id']) {
                            $isCorrectPaymentMethod = false;
                        }
                    }
                    if ($isCorrectPaymentMethod) {
                        $correctPaymentMethods[] = $paymentMethod;
                    }
                }
                $paymentMethods = $correctPaymentMethods;
            }
        }

        return $paymentMethods;
    }

    public static function getPaymentMethods($type = 'online', $total = null)
    {
        $paymentMethods = PaymentMethod::where('available_from_amount', '<=', $total ?: self::total())->where('site_id', Sites::getActive())->where('active', 1)->where('type', $type)->orderBy('order', 'asc')->get()->toArray();

        foreach ($paymentMethods as &$paymentMethod) {
            $paymentMethod['full_image_path'] = $paymentMethod['image'] ? Storage::disk('dashed')->url($paymentMethod['image']) : '';
            $paymentMethod['name'] = $paymentMethod['name'][app()->getLocale()] ?? '';
            $paymentMethod['additional_info'] = $paymentMethod['additional_info'][app()->getLocale()] ?? '';
            $paymentMethod['payment_instructions'] = $paymentMethod['payment_instructions'][app()->getLocale()] ?? '';
        }

        return $paymentMethods;
    }

    public static function getPaymentMethodsForDeposit($paymentMethodId)
    {
        $paymentMethod = PaymentMethod::find($paymentMethodId);

        $depositPaymentMethods = PaymentMethod::find($paymentMethod->deposit_calculation_payment_method_ids)->toArray();
        foreach ($depositPaymentMethods as &$depositPaymentMethod) {
            $depositPaymentMethod['full_image_path'] = $depositPaymentMethod['image'] ? Storage::disk('dashed')->url($depositPaymentMethod['image']) : '';
            $depositPaymentMethod['name'] = $depositPaymentMethod['name'][app()->getLocale()] ?? '';
            $depositPaymentMethod['additional_info'] = $depositPaymentMethod['additional_info'][app()->getLocale()] ?? '';
            $depositPaymentMethod['payment_instructions'] = $depositPaymentMethod['payment_instructions'][app()->getLocale()] ?? '';
        }

        return $depositPaymentMethods;
    }

    public static function removeInvalidItems($checkStock = true): void
    {
        $discountCode = DiscountCode::usable()->where('code', session('discountCode'))->first();

        if (! $discountCode || ! $discountCode->isValidForCart()) {
            session(['discountCode' => '']);
        }

        $cartItems = self::cartItems();
        $parentItemsToCheck = collect();

        // Loop through cart items
        foreach ($cartItems as $cartItem) {
            $cartItemDeleted = false;

            if (! $cartItem->model) {
                if ($cartItem->associatedModel) {
                    Cart::remove($cartItem->rowId);
                }

                continue;
            }

            $model = $cartItem->model;

            // Handle removed or unavailable products
            if ($model->trashed() || ! $model->publicShowable()) {
                Cart::remove($cartItem->rowId);
                $cartItemDeleted = true;

                Notification::make()
                    ->body(Translation::get('product-removed', 'cart', ':product: is uit je winkelwagen gehaald omdat het product niet meer beschikbaar is.', 'text', [
                        'product' => $model->name,
                    ]))
                    ->danger()
                    ->send();
            }

            // Handle stock checks
            if ($checkStock && ! $cartItemDeleted && $model->stock() < $cartItem->qty) {
                $newStock = $model->stock();
                if ($newStock > 0) {
                    Cart::update($cartItem->rowId, $newStock);
                    Notification::make()
                        ->body(Translation::get('product-less-stock', 'cart', ':product: is verlaagd in je winkelwagen omdat er maar :stock: voorraad is.', 'text', [
                            'product' => $model->name,
                            'stock' => $newStock,
                        ]))
                        ->danger()
                        ->send();
                } else {
                    Cart::remove($cartItem->rowId);
                    $cartItemDeleted = true;

                    Notification::make()
                        ->body(Translation::get('product-out-of-stock', 'cart', ':product: is uit je winkelwagen gehaald omdat er geen voorraad meer is.', 'text', [
                            'product' => $model->name,
                        ]))
                        ->danger()
                        ->send();
                }
            }

            // Handle purchase limits
            if (! $cartItemDeleted && $model->limit_purchases_per_customer && $cartItem->qty > $model->limit_purchases_per_customer_limit) {
                Cart::update($cartItem->rowId, $model->limit_purchases_per_customer_limit);
            }

            // Merge cart items with the same product and options
            if (! $cartItemDeleted) {
                foreach ($cartItems as $otherCartItem) {
                    if ($cartItem->rowId === $otherCartItem->rowId || ! $otherCartItem->model) {
                        continue;
                    }

                    if ($model->id === $otherCartItem->model->id && $cartItem->options === $otherCartItem->options) {
                        $newQuantity = $cartItem->qty + $otherCartItem->qty;

                        if ($model->limit_purchases_per_customer && $newQuantity > $model->limit_purchases_per_customer_limit) {
                            $newQuantity = $model->limit_purchases_per_customer_limit;
                        }

                        Cart::update($cartItem->rowId, $newQuantity);
                        Cart::remove($otherCartItem->rowId);
                    }
                }
            }

            // Collect parent product groups for stock checks
            if (! $cartItemDeleted && $model->productGroup && $model->productGroup->use_parent_stock ?? false) {
                $parentItemsToCheck->push($model->productGroup->id);
            }

            if (! $cartItemDeleted) {
                $price = $cartItem->options['originalPrice'];

                if ($model->volumeDiscounts) {
                    $volumeDiscount = $model->volumeDiscounts()->where('min_quantity', '<=', $cartItem->qty)->orderBy('min_quantity', 'desc')->first();
                    if ($volumeDiscount) {
                        if (! $cartItem->options['originalPrice']) {
                            Cart::update($cartItem->rowId, [
                                'options' => array_merge($cartItem->options, ['originalPrice' => $cartItem->price]),
                            ]);
                        }
                        $price = $volumeDiscount->getPrice($price);
                    }
                }

                Cart::update($cartItem->rowId, [
                    'price' => $price,
                ]);
            }
        }

        // Check parent product group stock
        $parentItemsToCheck->unique()->each(function ($parentId) {
            $parentProduct = Product::find($parentId);
            if (! $parentProduct) {
                return;
            }

            $cartItems = self::cartItems()->filter(function ($cartItem) use ($parentId) {
                return $cartItem->model && $cartItem->model->parent && $cartItem->model->parent->id === $parentId;
            });

            $maxStock = $parentProduct->stock();
            $maxLimit = $parentProduct->limit_purchases_per_customer_limit;
            $currentAmount = $cartItems->sum('qty');

            if ($currentAmount > $maxStock || $currentAmount > $maxLimit) {
                Notification::make()
                    ->danger()
                    ->title(Translation::get('parent-product-limit-reached', 'cart', 'You cannot have more than the allowed amount of this product in your cart'))
                    ->send();

                $cartItems->each(function ($cartItem) use ($maxStock) {
                    Cart::remove($cartItem->rowId);
                });
            }
        });
    }

    public static function emptyMyCart()
    {
        session(['discountCode' => '']);
        Cart::destroy();
    }

    public static function hasCartitemByRowId($rowId)
    {
        try {
            Cart::get($rowId);

            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    public static function getCheckoutData($shippingMethodId, $paymentMethodId)
    {
        $discount = self::totalDiscount(false);
        $tax = self::btw(false, true, $shippingMethodId, $paymentMethodId);
        $total = self::total(false, true, $shippingMethodId, $paymentMethodId, $tax, $discount);
        $subTotal = self::subtotal(false, $shippingMethodId, $paymentMethodId, $total);

        $taxPercentages = ShoppingCart::btwPercentages(false, true, $shippingMethodId, $paymentMethodId);
        $depositAmount = ShoppingCart::depositAmount(false, true, $shippingMethodId, $paymentMethodId, $total);
        $depositPaymentMethods = [];
        if ($depositAmount > 0.00) {
            $depositPaymentMethods = ShoppingCart::getPaymentMethodsForDeposit($paymentMethodId);
        }
        $shippingCosts = 0;
        $paymentCosts = 0;

        if ($shippingMethodId) {
            $shippingMethod = ShippingMethod::find($shippingMethodId);
            if ($shippingMethod) {
                $shippingCosts = $shippingMethod->costsForCart;
            }
        }

        $isPostPayMethod = false;
        if ($paymentMethodId) {
            foreach (ShoppingCart::getPaymentMethods(null, $total) as $paymentMethod) {
                if ($paymentMethod['id'] == $paymentMethodId) {
                    $paymentCosts = $paymentMethod['extra_costs'];
                    $isPostPayMethod = $paymentMethod['postpay'];
                }
            }
        }

        return [
            'subTotal' => $subTotal,
            'subTotalFormatted' => CurrencyHelper::formatPrice($subTotal),
            'discount' => $discount,
            'discountFormatted' => CurrencyHelper::formatPrice($discount),
            'btw' => $tax,
            'btwPercentages' => $taxPercentages,
            'btwFormatted' => CurrencyHelper::formatPrice($tax),
            'total' => $total,
            'totalFormatted' => CurrencyHelper::formatPrice($total),
            'shippingCosts' => $shippingCosts,
            'shippingCostsFormatted' => CurrencyHelper::formatPrice($shippingCosts),
            'paymentCosts' => $paymentCosts,
            'paymentCostsFormatted' => CurrencyHelper::formatPrice($paymentCosts),
            'postpayPaymentMethod' => $isPostPayMethod,
            'depositRequired' => $depositAmount > 0.00 ? true : false,
            'depositAmount' => $depositAmount,
            'depositAmountFormatted' => CurrencyHelper::formatPrice($depositAmount),
            'depositPaymentMethods' => $depositPaymentMethods,
        ];
    }

    public static function getCrossSellAndSuggestedProducts(int $limit = 4, bool $removeIfAlreadyPresentInShoppingCart = true): Collection
    {
        $suggestedProductIds = collect();

        $cartItems = self::cartItems();
        $productIdsInCart = $cartItems->pluck('model.id')->toArray();

        foreach ($cartItems as $cartItem) {
            $suggestedProductIds = $suggestedProductIds
                ->merge($cartItem->model->crossSellProducts?->pluck('id') ?? [])
                ->merge($cartItem->model->suggestedProducts?->pluck('id') ?? []);
        }

        if ($removeIfAlreadyPresentInShoppingCart) {
            $suggestedProductIds = $suggestedProductIds->diff($productIdsInCart);
        }

        $suggestedProductIds = $suggestedProductIds->unique();

        if ($suggestedProductIds->count() < $limit) {
            $additionalSuggestedProductIds = Product::publicShowable()
                ->whereNotIn('id', $suggestedProductIds->toArray())
                ->inRandomOrder()
                ->limit($limit - $suggestedProductIds->count())
                ->pluck('id');

            $suggestedProductIds = $suggestedProductIds->merge($additionalSuggestedProductIds);
        }

        return Product::whereIn('id', $suggestedProductIds->take($limit)->toArray())
            ->publicShowable()
            ->get();
    }
}
