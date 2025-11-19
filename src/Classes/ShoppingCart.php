<?php

namespace Dashed\DashedEcommerceCore\Classes;

use Exception;
use Illuminate\Support\Collection as SupportCollection;
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

    public static function cartItemsCount()
    {
        return Cart::count();
    }

    public static function getAvailableShippingMethods($countryName, string $shippingAddress = '', $paymentMethod = null)
    {
        $cartItems = cartHelper()->getCartItems();
        cartHelper()->preloadCartProducts(['productGroup']);
        $productIds = [];
        $productGroupIds = [];
        $activatedShippingMethodIds = [];

        if ($paymentMethod) {
            $paymentMethod = PaymentMethod::find($paymentMethod);
            if ($paymentMethod && $paymentMethod->shippingMethods->count()) {
                foreach ($paymentMethod->shippingMethods as $shippingMethod) {
                    $activatedShippingMethodIds[] = $shippingMethod->id;
                }
            }
        }

        foreach ($cartItems as $cartItem) {
            $product = cartHelper()->getProductForCartItem($cartItem);
            if ($product) {
                $productIds[] = $product->id;
                $productGroupIds[] = $product->product_group_id;
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
                    ->where('maximum_order_value', '>=', $total);
                //                    ->where(function ($query) use ($distanceRange) {
                //                        $query->where('distance_range_enabled', 1)
                //                            ->where('distance_range', '>=', $distanceRange);
                //                    })
                //                    ->orWhere('distance_range_enabled', 0)
                if (count($activatedShippingMethodIds)) {
                    $shippingMethods = $shippingMethods->whereIn('id', $activatedShippingMethodIds);
                }
                $shippingMethods = $shippingMethods->orderBy('order', 'ASC')->get();

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

    public static function getPaymentMethods(?string $type = 'online', ?float $total = null, ?int $userId = null, bool $skipTotalCheck = false): Collection
    {
        $userId = $userId ?: (auth()->check() ? auth()->user()->id : 0);

        $paymentMethods = PaymentMethod::where('site_id', Sites::getActive())->where('active', 1)->where('type', $type);
        if (!$skipTotalCheck) {
            $total = $total ?: cartHelper()->getTotal();
            $paymentMethods = $paymentMethods->where('available_from_amount', '<=', $total);
        }
        $paymentMethods = $paymentMethods->orderBy('order', 'asc')->get();

        foreach ($paymentMethods as $key => &$paymentMethod) {

            $paymentMethodValid = true;

            if ($userId && DB::table('dashed__payment_method_users')->where('payment_method_id', $paymentMethod->id)->count() > 0 && DB::table('dashed__payment_method_users')->where('payment_method_id', $paymentMethod->id)->where('user_id', $userId)->count() == 0) {
                $paymentMethodValid = false;
            } elseif (!$userId && DB::table('dashed__payment_method_users')->where('payment_method_id', $paymentMethod->id)->count() > 0) {
                $paymentMethodValid = false;
            }

            if (!$paymentMethodValid) {
                unset($paymentMethods[$key]);
            } else {
                $paymentMethod['full_image_path'] = $paymentMethod->image ? Storage::disk('dashed')->url($paymentMethod->image) : '';
                //                $paymentMethod['name'] = $paymentMethod['name'][app()->getLocale()] ?? '';
                //                $paymentMethod['additional_info'] = $paymentMethod['additional_info'][app()->getLocale()] ?? '';
                //                $paymentMethod['payment_instructions'] = $paymentMethod['payment_instructions'][app()->getLocale()] ?? '';
            }
        }

        return $paymentMethods;
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

    public static function getCrossSellAndSuggestedProducts(
        int  $limit = 4,
        bool $removeIfAlreadyPresentInShoppingCart = true
    ): Collection
    {
        $suggestedProductIds = collect();

        $cartItems = cartHelper()->getCartItems();

        // preload alle producten + relaties in 1x
        $productsById = cartHelper()->preloadProductsForCartItems(
            $cartItems,
            ['crossSellProducts:id', 'suggestedProducts:id'] // minimal columns
        );

        $productIdsInCart = [];

        foreach ($cartItems as $cartItem) {
            $product = $productsById[$cartItem->id] ?? null;

            if (!$product) {
                continue;
            }

            $suggestedProductIds = $suggestedProductIds
                ->merge($product->crossSellProducts?->pluck('id') ?? [])
                ->merge($product->suggestedProducts?->pluck('id') ?? []);

            $productIdsInCart[] = $product->id;
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


    public static function getCrossSellProducts(
        int  $limit = 4,
        bool $removeIfAlreadyPresentInShoppingCart = true
    ): Collection|SupportCollection
    {
        $crossSellProductIds = collect();

        $cartItems = cartHelper()->getCartItems();

        $productsById = cartHelper()->preloadProductsForCartItems(
            $cartItems,
            ['crossSellProducts:id'] // alleen wat je nodig hebt
        );

        $productIdsInCart = [];

        foreach ($cartItems as $cartItem) {
            $product = $productsById[$cartItem->id] ?? null;

            if (!$product) {
                continue;
            }

            $crossSellProductIds = $crossSellProductIds
                ->merge($product->crossSellProducts?->pluck('id') ?? []);

            $productIdsInCart[] = $product->id;
        }

        if ($removeIfAlreadyPresentInShoppingCart) {
            $crossSellProductIds = $crossSellProductIds->diff($productIdsInCart);
        }

        $crossSellProductIds = $crossSellProductIds->unique()->take($limit);

        if ($crossSellProductIds->isEmpty()) {
            return collect();
        }

        return Product::whereIn('id', $crossSellProductIds->toArray())
            ->publicShowable()
            ->get();
    }
}
