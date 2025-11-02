<?php

namespace Dashed\DashedEcommerceCore\Classes;

use Exception;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Dashed\DashedPages\Models\Page;
use Dashed\DashedCore\Classes\Sites;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
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

    //    public static function cartItems(?string $cartType = null)
    //    {
    //        if ($cartType) {
    //            self::setInstance($cartType);
    //        }
    //
    //        return Cart::content();
    //    }

    public static function cartItemsCount()
    {
        return Cart::count();
    }

    //    public static function setInstance(string $cartType = 'default')
    //    {
    //        Cart::instance($cartType);
    //    }

    //    public static function totalDiscount($formatResult = false, ?string $discountCodeToUse = null, $shippingMethodId = null, $paymentMethodId = null, ?int $shippingZoneId = null)
    //    {
    //        $totalDiscount = 0;
    //
    //        //        ray('session ' . session('discountCode'));
    //        $discountCode = $discountCodeToUse ?: session('discountCode');
    //        //        ray($discountCode);
    //        if ($discountCode) {
    //            $discountCode = DiscountCode::usable()->where('code', $discountCode)->first();
    //
    //            if (! $discountCode || ! $discountCode->isValidForCart()) {
    //                session(['discountCode' => '']);
    //            } else {
    //                if ($discountCode->type == 'percentage') {
    //                    $itemsInCart = self::cartItems();
    //
    //                    foreach ($itemsInCart as $item) {
    //                        //                        $discountedPrice = $discountCode->getDiscountedPriceForProduct($item->model, $item->qty);
    //                        $totalDiscount += Product::getShoppingCartItemPrice($item) - Product::getShoppingCartItemPrice($item, $discountCode);
    //                    }
    //                } elseif ($discountCode->type == 'amount') {
    //                    $totalDiscount = $discountCode->discount_amount;
    //                }
    //            }
    //        }
    //
    //        $total = self::total(false, false, $shippingMethodId, $paymentMethodId, shippingZoneId: $shippingZoneId);
    //        if ($totalDiscount > $total) {
    //            $totalDiscount = $total - 0.01;
    //        }
    //
    //        if ($totalDiscount) {
    //            if ($formatResult) {
    //                return CurrencyHelper::formatPrice($totalDiscount);
    //            } else {
    //                return number_format($totalDiscount, 2, '.', '');
    //            }
    //        } else {
    //            return 0;
    //        }
    //    }

    //    public static function subtotal($formatResult = false, $shippingMethodId = null, $paymentMethodId = null, $total = null, ?int $shippingZoneId = null)
    //    {
    //        $cartTotal = $total ?: self::total(false, false, $shippingMethodId, $paymentMethodId);
    //
    //        $calculateInclusiveTax = Customsetting::get('taxes_prices_include_taxes');
    //        if (! $calculateInclusiveTax) {
    //            $cartTotal -= self::btw(false, false, $shippingMethodId, $paymentMethodId, shippingZoneId: $shippingZoneId);
    //
    //            if ($shippingMethodId) {
    //                $shippingMethod = ShippingMethod::find($shippingMethodId);
    //                if ($shippingMethod) {
    //                    $cartTotal -= $shippingMethod->costsForCart($shippingZoneId);
    //                }
    //            }
    //
    //            if ($paymentMethodId) {
    //                foreach (ShoppingCart::getPaymentMethods() as $paymentMethod) {
    //                    if ($paymentMethod['id'] == $paymentMethodId) {
    //                        $cartTotal -= $paymentMethod['extra_costs'];
    //                    }
    //                }
    //            }
    //        }
    //
    //        if ($cartTotal < 0) {
    //            $cartTotal = 0.01;
    //        }
    //
    //        if ($formatResult) {
    //            return CurrencyHelper::formatPrice($cartTotal);
    //        } else {
    //            return number_format($cartTotal, 2, '.', '');
    //        }
    //    }

    //    public static function total($formatResult = false, $calculateDiscount = true, $shippingMethodId = null, $paymentMethodId = null, $tax = null, $discount = null, ?int $shippingZoneId = null)
    //    {
    //        $cartTotal = 0;
    //        foreach (self::cartItems() as $cartItem) {
    //            $cartTotal += $cartItem->model ? Product::getShoppingCartItemPrice($cartItem) : ($cartItem->price * $cartItem->qty);
    //            //            $cartTotal = $cartTotal + ($cartItem->model->currentPrice * $cartItem->qty);
    //        }
    //
    //        if ($calculateDiscount) {
    //            $cartTotal = $cartTotal - ($discount ?: self::totalDiscount(shippingMethodId: $shippingMethodId, paymentMethodId: $paymentMethodId, shippingZoneId: $shippingZoneId));
    //        }
    //
    //        $calculateInclusiveTax = Customsetting::get('taxes_prices_include_taxes');
    //        if (! $calculateInclusiveTax) {
    //            $tax = $tax ?: self::btw(false, $calculateDiscount, $shippingMethodId, $paymentMethodId);
    //            $cartTotal = $cartTotal + $tax;
    //        }
    //
    //        if ($shippingMethodId) {
    //            $shippingMethod = ShippingMethod::find($shippingMethodId);
    //            if ($shippingMethod) {
    //                //                dump($cartTotal);
    //                $cartTotal += $shippingMethod->costsForCart($shippingZoneId);
    //                //                dd($cartTotal);
    //            }
    //        }
    //
    //        if ($paymentMethodId) {
    //            foreach (ShoppingCart::getPaymentMethods() as $paymentMethod) {
    //                if ($paymentMethod['id'] == $paymentMethodId) {
    //                    $cartTotal += $paymentMethod['extra_costs'];
    //                }
    //            }
    //        }
    //
    //        if ($cartTotal < 0) {
    //            $cartTotal = 0.01;
    //        }
    //
    //        if ($formatResult) {
    //            return CurrencyHelper::formatPrice($cartTotal);
    //        } else {
    //            return number_format($cartTotal, 2, '.', '');
    //        }
    //    }

    //    public static function depositAmount($formatResult = false, $calculateDiscount = true, $shippingMethodId = null, $paymentMethodId = null, $total = null, ?int $shippingZoneId = null)
    //    {
    //        $depositAmount = 0;
    //
    //        if ($paymentMethodId) {
    //            foreach (ShoppingCart::getPaymentMethods() as $paymentMethod) {
    //                if ($paymentMethod['id'] == $paymentMethodId) {
    //                    if ($paymentMethod['deposit_calculation']) {
    //                        $paymentMethod['deposit_calculation'] = str_replace('{ORDER_TOTAL_MINUS_PAYMENT_COSTS}', $total ?: self::total(false, $calculateDiscount, $shippingMethodId, null, shippingZoneId: $shippingZoneId), $paymentMethod['deposit_calculation']);
    //                        $paymentMethod['deposit_calculation'] = str_replace('{ORDER_TOTAL}', $total ?: self::total(false, $calculateDiscount, $shippingMethodId, $paymentMethodId, shippingZoneId: $shippingZoneId), $paymentMethod['deposit_calculation']);
    //                        $depositAmount = eval('return ' . $paymentMethod['deposit_calculation'] . ';');
    //                    }
    //                }
    //            }
    //        }
    //
    //        if ($formatResult) {
    //            return CurrencyHelper::formatPrice($depositAmount);
    //        } else {
    //            return number_format($depositAmount, 2, '.', '');
    //        }
    //    }

    //    public static function amounts($formatResult = false, $calculateDiscount = true, $shippingMethodId = null, $paymentMethodId = null, ?int $shippingZoneId = null)
    //    {
    //        $discount = self::totalDiscount(false, null, $shippingMethodId, $paymentMethodId, shippingZoneId: $shippingZoneId);
    //        $tax = self::btw(false, $calculateDiscount, $shippingMethodId, $paymentMethodId, shippingZoneId: $shippingZoneId);
    //        $total = self::total(false, true, $shippingMethodId, $paymentMethodId, $tax, $discount, shippingZoneId: $shippingZoneId);
    //        $subTotal = self::subtotal(false, $shippingMethodId, $paymentMethodId, $total, shippingZoneId: $shippingZoneId);
    //
    //        if ($formatResult) {
    //            return [
    //                'subTotal' => CurrencyHelper::formatPrice($subTotal),
    //                'discount' => CurrencyHelper::formatPrice($discount),
    //                'tax' => CurrencyHelper::formatPrice($tax),
    //                'total' => CurrencyHelper::formatPrice($total),
    ////                'shippingCosts' => CurrencyHelper::formatPrice($shippingCosts),
    ////                'paymentCosts' => CurrencyHelper::formatPrice($paymentCosts),
    ////                'depositAmount' => CurrencyHelper::formatPrice($depositAmount),
    //            ];
    //        } else {
    //            return [
    //                'subTotal' => number_format($subTotal, 2, '.', ''),
    //                'discount' => number_format($discount, 2, '.', ''),
    //                'tax' => number_format($tax, 2, '.', ''),
    //                'total' => number_format($total, 2, '.', ''),
    ////                'shippingCosts' => number_format($shippingCosts, 2, '.', ''),
    ////                'paymentCosts' => number_format($paymentCosts, 2, '.', ''),
    ////                'depositAmount' => number_format($depositAmount, 2, '.', ''),
    //            ];
    //        }
    //    }

    //    public static function btw($formatResult = false, $calculateDiscount = true, $shippingMethodId = null, $paymentMethodId = null, ?int $shippingZoneId = null)
    //    {
    //        $calculateInclusiveTax = Customsetting::get('taxes_prices_include_taxes');
    //        $baseVatInfo = self::getVatBaseInfoForCalculation($calculateDiscount);
    //        $discountCode = $baseVatInfo['discountCode'];
    //
    //        $taxTotal = $baseVatInfo['taxTotal'];
    //
    //        if ($discountCode && $discountCode->type == 'amount') {
    //            if ($calculateInclusiveTax) {
    //                foreach ($baseVatInfo['vatPercentageOfTotals'] as $percentage => $vatPercentageOfTotal) {
    //                    if ($vatPercentageOfTotal) {
    //                        $taxTotal -= (($discountCode->discount_amount * ($vatPercentageOfTotal / 100)) / (100 + $percentage) * $percentage);
    //                    }
    //                }
    //            } else {
    //                foreach ($baseVatInfo['vatPercentageOfTotals'] as $percentage => $vatPercentageOfTotal) {
    //                    if ($vatPercentageOfTotal) {
    //                        $taxTotal -= (($discountCode->discount_amount * ($vatPercentageOfTotal / 100)) / 100 * $percentage);
    //                    }
    //                }
    //            }
    //        }
    //
    //        if ($shippingMethodId) {
    //            $taxTotal += self::vatForShippingMethod($shippingMethodId, false, $calculateDiscount, shippingZoneId: $shippingZoneId);
    //        }
    //
    //        if ($paymentMethodId) {
    //            $taxTotal += self::vatForPaymentMethod($paymentMethodId);
    //        }
    //
    //        if ($taxTotal < 0) {
    //            $taxTotal = 0;
    //        }
    //
    //        if ($formatResult) {
    //            return CurrencyHelper::formatPrice($taxTotal);
    //        } else {
    //            return number_format($taxTotal, 2, '.', '');
    //        }
    //    }

    //    public static function btwPercentages($formatResult = false, $calculateDiscount = true, $shippingMethodId = null, $paymentMethodId = null, $discount = 0, ?int $shippingZoneId = null): array
    //    {
    //        $calculateInclusiveTax = Customsetting::get('taxes_prices_include_taxes');
    //        $baseVatInfo = self::getVatBaseInfoForCalculation($calculateDiscount);
    //        $discountCode = $baseVatInfo['discountCode'];
    //
    //        $totalVatPerPercentage = $baseVatInfo['totalVatPerPercentage'];
    //
    //        if ($discountCode && $discountCode->type == 'amount') {
    //            if (! $discount) {
    //                $discount = $discountCode->discount_amount;
    //            }
    //
    //            if ($calculateInclusiveTax) {
    //                foreach ($baseVatInfo['vatPercentageOfTotals'] as $percentage => $vatPercentageOfTotal) {
    //                    if ($vatPercentageOfTotal) {
    //                        foreach ($totalVatPerPercentage as $percentage => $value) {
    //                            $totalVatPerPercentage[$percentage] -= (($discount * ($vatPercentageOfTotal / 100)) / (100 + $percentage) * $percentage);
    //                        }
    //                    }
    //                }
    //            } else {
    //                foreach ($baseVatInfo['vatPercentageOfTotals'] as $percentage => $vatPercentageOfTotal) {
    //                    if ($vatPercentageOfTotal) {
    //                        foreach ($totalVatPerPercentage as $percentage => $value) {
    //                            $totalVatPerPercentage[$percentage] -= (($discount * ($vatPercentageOfTotal / 100)) / 100 * $percentage);
    //                        }
    //                    }
    //                }
    //            }
    //        }
    //
    //        if ($shippingMethodId) {
    //            foreach ($totalVatPerPercentage as $percentage => $value) {
    //                $result = self::vatForShippingMethod($shippingMethodId, false, $calculateDiscount, $percentage, shippingZoneId: $shippingZoneId);
    //                //                $result = self::vatForShippingMethod($shippingMethodId, false, $calculateDiscount, $percentage) / 100 * $baseVatInfo['vatPercentageOfTotals'][$percentage];
    //                $totalVatPerPercentage[$percentage] += $result;
    //            }
    //        }
    //
    //        if ($paymentMethodId) {
    //            $paymentVat = self::vatForPaymentMethod($paymentMethodId);
    //            isset($totalVatPerPercentage[21]) ? $totalVatPerPercentage[21] += $paymentVat : $totalVatPerPercentage[21] = $paymentVat;
    //        }
    //
    //        foreach ($totalVatPerPercentage as $percentage => $value) {
    //            $totalVatPerPercentage[$percentage] = round($value, 2);
    //        }
    //
    //        return $totalVatPerPercentage;
    //    }

    //    public static function vatForShippingMethod($shippingMethodId, $formatResult = false, $calculateDiscount = true, $vatRate = null, ?int $shippingZoneId = null)
    //    {
    //        $calculateInclusiveTax = Customsetting::get('taxes_prices_include_taxes');
    //
    //        if (! $vatRate) {
    //            $vatRate = self::vatRateForShippingMethod($shippingMethodId);
    //        }
    //
    //        $taxTotal = 0;
    //
    //        $shippingMethod = ShippingMethod::find($shippingMethodId);
    //        if ($shippingMethod) {
    //            if ($calculateInclusiveTax) {
    //                $taxTotal += $shippingMethod->costsForCart($shippingZoneId) / (100 + $vatRate) * $vatRate;
    //            } else {
    //                $taxTotal += $shippingMethod->costsForCart($shippingZoneId) / 100 * $vatRate;
    //            }
    //        }
    //
    //        return round($taxTotal, 2);
    //    }

    //    public static function vatRateForShippingMethod($shippingMethodId)
    //    {
    //        $baseVatInfo = self::getVatBaseInfoForCalculation();
    //
    //        $shippingMethod = ShippingMethod::find($shippingMethodId);
    //        if ($shippingMethod && $baseVatInfo['vatRatesCount']) {
    //            return round($baseVatInfo['vatRates'] / $baseVatInfo['vatRatesCount'], 2);
    //        }
    //
    //        return 0;
    //    }

    //    public static function vatForPaymentMethod($paymentMethodId)
    //    {
    //        $calculateInclusiveTax = Customsetting::get('taxes_prices_include_taxes');
    //
    //        $taxTotal = 0;
    //
    //        foreach (ShoppingCart::getPaymentMethods() as $paymentMethod) {
    //            if ($paymentMethod['id'] == $paymentMethodId) {
    //                if ($calculateInclusiveTax) {
    //                    $taxTotal = $taxTotal + ($paymentMethod['extra_costs'] / 121 * 21);
    //                } else {
    //                    $taxTotal = $taxTotal + ($paymentMethod['extra_costs'] / 100 * 21);
    //                }
    //            }
    //        }
    //
    //        return $taxTotal;
    //    }

    //    public static function getVatBaseInfoForCalculation($calculateDiscount = true): array
    //    {
    //        $calculateInclusiveTax = Customsetting::get('taxes_prices_include_taxes');
    //        $taxTotal = 0;
    //        $vatRates = 0;
    //        $vatRatesCount = 0;
    //
    //        if ($calculateDiscount) {
    //            $discountCode = DiscountCode::usable()->isNotGlobalDiscount()->where('code', session('discountCode'))->first();
    //
    //            if (! $discountCode || ! $discountCode->isValidForCart()) {
    //                session(['discountCode' => '']);
    //                $discountCode = null;
    //            }
    //        } else {
    //            $discountCode = null;
    //        }
    //
    //        $totalAmountForVats = [];
    //
    //        $totalPriceForProducts = 0;
    //
    //        foreach (self::cartItems() as $cartItem) {
    //            if ($cartItem->model || ($cartItem->options['customProduct'] ?? false)) {
    //                //                $isBundleItemWithIndividualPricing = false;
    //                $cartProducts = [$cartItem->model ?? $cartItem];
    //                if ($cartItem->model && $cartItem->model->is_bundle && $cartItem->model->use_bundle_product_price) {
    //                    //                    $isBundleItemWithIndividualPricing = true;
    //                    $cartProducts = $cartItem->model->bundleProducts;
    //                }
    //
    //                foreach ($cartProducts as $cartProduct) {
    //                    if ($discountCode && $discountCode->type == 'percentage') {
    //                        $price = Product::getShoppingCartItemPrice($cartItem, $discountCode);
    //                    } else {
    //                        $price = Product::getShoppingCartItemPrice($cartItem);
    //                    }
    //                    //                    dump($price);
    //                    $totalPriceForProducts += $price;
    //
    //                    //                    dump($price);
    //                    if ($calculateInclusiveTax) {
    //                        $price = $price / (100 + ($cartProduct->options['vat_rate'] ?? $cartProduct->vat_rate)) * ($cartProduct->options['vat_rate'] ?? $cartProduct->vat_rate);
    //                    } else {
    //                        $price = $price / 100 * ($cartProduct->options['vat_rate'] ?? $cartProduct->vat_rate);
    //                    }
    //                    //                    dump($price);
    //
    //                    $taxTotal += $price;
    //                    if (($cartProduct->options['vat_rate'] ?? $cartProduct->vat_rate) > 0) {
    //                        //                        dump($cartProduct->options['vat_rate'] ?? $cartProduct->vat_rate);
    //                        if (! isset($totalAmountForVats[number_format(($cartProduct->options['vat_rate'] ?? $cartProduct->vat_rate), 0)])) {
    //                            $totalAmountForVats[number_format(($cartProduct->options['vat_rate'] ?? $cartProduct->vat_rate), 0)] = 0;
    //                        }
    //                        if ($discountCode && $discountCode->type == 'percentage') {
    //                            $totalCartItemAmount = Product::getShoppingCartItemPrice($cartItem, $discountCode);
    //                            if (! $calculateInclusiveTax) {
    //                                $totalCartItemAmount = $totalCartItemAmount / 100 * (100 + ($cartProduct->options['vat_rate'] ?? $cartProduct->vat_rate));
    //                            }
    //
    //                            $totalAmountForVats[number_format(($cartProduct->options['vat_rate'] ?? $cartProduct->vat_rate), 0)] += $totalCartItemAmount;
    //                        } else {
    //                            $totalCartItemAmount = Product::getShoppingCartItemPrice($cartItem);
    //                            if (! $calculateInclusiveTax) {
    //                                $totalCartItemAmount = $totalCartItemAmount / 100 * (100 + ($cartProduct->options['vat_rate'] ?? $cartProduct->vat_rate));
    //                            }
    //
    //                            $totalAmountForVats[number_format(($cartProduct->options['vat_rate'] ?? $cartProduct->vat_rate), 0)] += $totalCartItemAmount;
    //                        }
    //                        //                        $totalAmountForVats[($cartProduct->options['vat_rate'] ?? $cartProduct->vat_rate)] += (($isBundleItemWithIndividualPricing ? $cartProduct->currentPrice : $cartItem->model->currentPrice) * $cartItem->qty);
    //                        $vatRates += ($cartProduct->options['vat_rate'] ?? $cartProduct->vat_rate) * $cartItem->qty;
    //                        $vatRatesCount += $cartItem->qty;
    //                    }
    //                }
    //            } else {
    //                //                dd($cartItem);
    //            }
    //        }
    //
    //        $vatPercentageOfTotals = [];
    //        $totalVatPerPercentage = [];
    //
    //        foreach ($totalAmountForVats as $percentage => $totalAmountForVat) {
    //            if (! isset($vatPercentageOfTotals[number_format($percentage, 0)])) {
    //                $vatPercentageOfTotals[number_format($percentage, 0)] = 0;
    //            }
    //            if (! isset($totalVatPerPercentage[number_format($percentage, 0)])) {
    //                $totalVatPerPercentage[number_format($percentage, 0)] = 0;
    //            }
    //            $vatPercentageOfTotals[number_format($percentage, 0)] += $totalAmountForVat > 0.00 && $totalPriceForProducts > 0.00 ? ($totalAmountForVat / $totalPriceForProducts) * 100 : 0;
    //            $totalVatPerPercentage[number_format($percentage, 0)] += $totalAmountForVat > 0.00 ? ($totalAmountForVat / (100 + $percentage) * $percentage) : 0;
    //        }
    //
    //        return [
    //            'totalAmountForVats' => $totalAmountForVats,
    //            'totalVatPerPercentage' => $totalVatPerPercentage,
    //            'vatPercentageOfTotals' => $vatPercentageOfTotals,
    //            'vatRates' => $vatRates,
    //            'vatRatesCount' => $vatRatesCount,
    //            'taxTotal' => $taxTotal,
    //            'discountCode' => $discountCode,
    //        ];
    //    }

    public static function getAvailableShippingMethods($countryName, string $shippingAddress = '')
    {
        $cartItems = cartHelper()->getCartItems();
        $productIds = [];
        $productGroupIds = [];

        foreach ($cartItems as $cartItem) {
            if ($cartItem->model) {
                $productIds[] = $cartItem->model->id;
                $productGroupIds[] = $cartItem->model->product_group_id;
            }
        }

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

            if (!$shippingZoneIsActive && $shippingZone->search_fields) {
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

                $total = cartHelper()->getTotal();
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

                    if ($shippingMethodValid && $shippingMethod->disabledProducts()->whereIn('product_id', $productIds)->count()) {
                        $shippingMethodValid = false;
                    }
                    if ($shippingMethodValid && $shippingMethod->disabledProductGroups()->whereIn('product_group_id', $productGroupIds)->count()) {
                        $shippingMethodValid = false;
                    }

                    if ($shippingMethodValid) {
                        $shippingMethod->correctName = $shippingMethod->getTranslation('name', app()->getLocale());
                        $shippingMethod->shippingZoneId = $shippingZone->id;
                        $costs = $shippingMethod->costsForCart($shippingZone->id);
                        $shippingMethod->activatedShippingClasses = $shippingMethod->getActivatedShippingClasses($shippingZone->id);

                        $shippingMethod->costs = $costs;
                        if ($shippingMethod->costs == 0) {
                            $shippingMethod->costsFormatted = Translation::get('free', 'checkout', 'Gratis');
                        } elseif ($shippingMethod->costs > 0 && $shippingMethod->sort == 'free_delivery') {
                            unset($shippingMethods[$key]);
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

    public static function getAllShippingMethods($countryName)
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

            if (!$shippingZoneIsActive && $shippingZone->search_fields) {
                $searchFields = explode(',', $shippingZone->search_fields);
                foreach ($searchFields as $searchField) {
                    $searchField = trim($searchField);
                    if (strtolower($searchField) == strtolower($countryName)) {
                        $shippingZoneIsActive = true;
                    }
                }
            }

            if ($shippingZoneIsActive) {
                $shippingMethods = $shippingZone->shippingMethods()
                    ->orderBy('order', 'ASC')
                    ->get();

                foreach ($shippingMethods as $key => $shippingMethod) {
                    $shippingMethod->correctName = $shippingMethod->getTranslation('name', app()->getLocale()) . ' (' . $shippingZone->name . ')';
                    $shippingMethod->shippingZoneId = $shippingZone->id;
                    $costs = $shippingMethod->costsForCart($shippingZone->id);
                    $shippingMethod->activatedShippingClasses = $shippingMethod->getActivatedShippingClasses($shippingZone->id);

                    $shippingMethod->costs = $costs;
                    if ($shippingMethod->costs == 0) {
                        $shippingMethod->costsFormatted = Translation::get('free', 'checkout', 'Gratis');
                    } elseif ($shippingMethod->costs > 0 && $shippingMethod->sort == 'free_delivery') {
                        unset($shippingMethods[$key]);
                    } else {
                        $shippingMethod->costsFormatted = CurrencyHelper::formatPrice($costs);
                    }
                }

                return $shippingMethods;
            }
        }

        return [];
    }

    public static function getAvailablePaymentMethods($countryName, ?int $userId = null)
    {
        $paymentMethods = self::getPaymentMethods(userId: $userId);
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

            if (!$shippingZoneIsActive && $shippingZone->search_fields) {
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

    public static function getShippingZoneByCountry(?string $countryName): ?ShippingZone
    {
        if (!$countryName) {
            return null;
        }

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

            if (!$shippingZoneIsActive && $shippingZone->search_fields) {
                $searchFields = explode(',', $shippingZone->search_fields);
                foreach ($searchFields as $searchField) {
                    $searchField = trim($searchField);
                    if (strtolower($searchField) == strtolower($countryName)) {
                        $shippingZoneIsActive = true;
                    }
                }
            }

            if ($shippingZoneIsActive) {
                return $shippingZone;
            }
        }

        return null;
    }

    public static function getPaymentMethods(?string $type = 'online', float $total = null, ?int $userId = null, bool $skipTotalCheck = false, ): array
    {
        $total = $total ?: cartHelper()->getTotal();
        $userId = $userId ?: (auth()->check() ? auth()->user()->id : 0);

        $paymentMethods = PaymentMethod::where('site_id', Sites::getActive())->where('active', 1)->where('type', $type);
        if (!$skipTotalCheck) {
            $paymentMethods = $paymentMethods->where('available_from_amount', '<=', $total);
        }
        $paymentMethods = $paymentMethods->orderBy('order', 'asc')->get()->toArray();

        foreach ($paymentMethods as $key => &$paymentMethod) {

            $paymentMethodValid = true;

            if ($userId && DB::table('dashed__payment_method_users')->where('payment_method_id', $paymentMethod['id'])->count() > 0 && DB::table('dashed__payment_method_users')->where('payment_method_id', $paymentMethod['id'])->where('user_id', $userId)->count() == 0) {
                $paymentMethodValid = false;
            }

            if (!$paymentMethodValid) {
                unset($paymentMethods[$key]);
            } else {
                $paymentMethod['full_image_path'] = $paymentMethod['image'] ? Storage::disk('dashed')->url($paymentMethod['image']) : '';
                $paymentMethod['name'] = $paymentMethod['name'][app()->getLocale()] ?? '';
                $paymentMethod['additional_info'] = $paymentMethod['additional_info'][app()->getLocale()] ?? '';
                $paymentMethod['payment_instructions'] = $paymentMethod['payment_instructions'][app()->getLocale()] ?? '';
            }
        }

        return $paymentMethods;
    }

    //    public static function getPaymentMethodsForDeposit($paymentMethodId)
    //    {
    //        $paymentMethod = PaymentMethod::find($paymentMethodId);
    //
    //        $depositPaymentMethods = PaymentMethod::find($paymentMethod->deposit_calculation_payment_method_ids)->toArray();
    //        foreach ($depositPaymentMethods as &$depositPaymentMethod) {
    //            $depositPaymentMethod['full_image_path'] = $depositPaymentMethod['image'] ? Storage::disk('dashed')->url($depositPaymentMethod['image']) : '';
    //            $depositPaymentMethod['name'] = $depositPaymentMethod['name'][app()->getLocale()] ?? '';
    //            $depositPaymentMethod['additional_info'] = $depositPaymentMethod['additional_info'][app()->getLocale()] ?? '';
    //            $depositPaymentMethod['payment_instructions'] = $depositPaymentMethod['payment_instructions'][app()->getLocale()] ?? '';
    //        }
    //
    //        return $depositPaymentMethods;
    //    }

    //    public static function removeInvalidItems($checkStock = true): void
    //    {
    //        cartHelper()->removeInvalidItems($checkStock);
    //    }

    //    public static function emptyMyCart()
    //    {
    //        session(['discountCode' => '']);
    //        Cart::destroy();
    //    }

    public static function hasCartitemByRowId($rowId)
    {
        try {
            Cart::get($rowId);

            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    //    public static function getCheckoutData($shippingMethodId, $paymentMethodId, ?int $shippingZoneId = null)
    //    {
    //        throw new Exception('Deprecated, use cartHelper() method instead.');
    //        $discount = self::totalDiscount(false, null, $shippingMethodId, $paymentMethodId, shippingZoneId: $shippingZoneId);
    //        $tax = self::btw(false, true, $shippingMethodId, $paymentMethodId, shippingZoneId: $shippingZoneId);
    //        $total = self::total(false, true, $shippingMethodId, $paymentMethodId, $tax, $discount, shippingZoneId: $shippingZoneId);
    //        $subTotal = self::subtotal(false, $shippingMethodId, $paymentMethodId, $total, shippingZoneId: $shippingZoneId);
    //
    //        $taxPercentages = ShoppingCart::btwPercentages(false, true, $shippingMethodId, $paymentMethodId, shippingZoneId: $shippingZoneId);
    //        $depositAmount = ShoppingCart::depositAmount(false, true, $shippingMethodId, $paymentMethodId, $total, shippingZoneId: $shippingZoneId);
    //        $depositPaymentMethods = [];
    //        if ($depositAmount > 0.00) {
    //            $depositPaymentMethods = ShoppingCart::getPaymentMethodsForDeposit($paymentMethodId);
    //        }
    //        $shippingCosts = 0;
    //        $paymentCosts = 0;
    //
    //        if ($shippingMethodId) {
    //            $shippingMethod = ShippingMethod::find($shippingMethodId);
    //            if ($shippingMethod) {
    //                $shippingCosts = $shippingMethod->costsForCart($shippingZoneId);
    //            }
    //        }
    //
    //        $isPostPayMethod = false;
    //        if ($paymentMethodId) {
    //            foreach (ShoppingCart::getPaymentMethods(null, $total) as $paymentMethod) {
    //                if ($paymentMethod['id'] == $paymentMethodId) {
    //                    $paymentCosts = $paymentMethod['extra_costs'];
    //                    $isPostPayMethod = $paymentMethod['postpay'];
    //                }
    //            }
    //        }
    //
    //        return [
    //            'subTotal' => $subTotal,
    //            'subTotalFormatted' => CurrencyHelper::formatPrice($subTotal),
    //            'discount' => $discount,
    //            'discountFormatted' => CurrencyHelper::formatPrice($discount),
    //            'btw' => $tax,
    //            'btwPercentages' => $taxPercentages,
    //            'btwFormatted' => CurrencyHelper::formatPrice($tax),
    //            'total' => $total,
    //            'totalFormatted' => CurrencyHelper::formatPrice($total),
    //            'shippingCosts' => $shippingCosts,
    //            'shippingCostsFormatted' => CurrencyHelper::formatPrice($shippingCosts),
    //            'paymentCosts' => $paymentCosts,
    //            'paymentCostsFormatted' => CurrencyHelper::formatPrice($paymentCosts),
    //            'postpayPaymentMethod' => $isPostPayMethod,
    //            'depositRequired' => $depositAmount > 0.00 ? true : false,
    //            'depositAmount' => $depositAmount,
    //            'depositAmountFormatted' => CurrencyHelper::formatPrice($depositAmount),
    //            'depositPaymentMethods' => $depositPaymentMethods,
    //        ];
    //    }

    public static function getCrossSellAndSuggestedProducts(int $limit = 4, bool $removeIfAlreadyPresentInShoppingCart = true): Collection
    {
        $suggestedProductIds = collect();

        $cartItems = cartHelper()->getCartItems();
        $productIdsInCart = [];

        foreach ($cartItems as $cartItem) {
            $suggestedProductIds = $suggestedProductIds
                ->merge($cartItem->model->crossSellProducts?->pluck('id') ?? [])
                ->merge($cartItem->model->suggestedProducts?->pluck('id') ?? []);
            $productIdsInCart[] = $cartItem->model->id;
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

    public static function getCrossSellProducts(int $limit = 4, bool $removeIfAlreadyPresentInShoppingCart = true): Collection
    {
        $crossSellProductIds = collect();

        $cartItems = cartHelper()->getCartItems();
        $productIdsInCart = [];

        foreach ($cartItems as $cartItem) {
            $crossSellProductIds = $crossSellProductIds
                ->merge($cartItem->model->getCrossSellProducts(true)->pluck('id') ?? []);
            $productIdsInCart[] = $cartItem->model->id;
        }

        if ($removeIfAlreadyPresentInShoppingCart) {
            $crossSellProductIds = $crossSellProductIds->diff($productIdsInCart);
        }

        $crossSellProductIds = $crossSellProductIds->unique();

        return Product::whereIn('id', $crossSellProductIds->take($limit)->toArray())
            ->publicShowable()
            ->get();
    }
}
