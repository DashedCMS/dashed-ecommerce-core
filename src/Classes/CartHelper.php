<?php

namespace Dashed\DashedEcommerceCore\Classes;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Filament\Notifications\Notification;
use Gloudemans\Shoppingcart\Facades\Cart;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedTranslations\Models\Translation;
use Dashed\DashedEcommerceCore\Models\DiscountCode;
use Dashed\DashedEcommerceCore\Models\PaymentMethod;
use Dashed\DashedEcommerceCore\Models\ShippingMethod;
use Dashed\DashedEcommerceCore\Models\EcommerceActionLog;

class CartHelper
{
    public static float $total = 0;
    public static float $totalWithoutDiscount = 0;
    public static float $subtotal = 0;
    public static float $discount = 0;
    public static float $tax = 0;
    public static float $taxWithoutDiscount = 0;
    public static float $shippingCosts = 0;
    public static float $paymentCosts = 0;
    public static float $depositAmount = 0;
    public static bool $isPostPayMethod = false;
    public static ?array $totalAmountForVats = [];
    public static ?array $totalVatPerPercentage = [];
    public static ?array $vatPercentageOfTotals = [];
    public static int $vatRates = 0;
    public static array $taxPercentages = [];
    public static int $vatRatesCount = 0;
    public static ?DiscountCode $discountCode = null;
    public static ?string $discountCodeString = null;
    public static bool $calculateInclusiveTax = false;

    public static ?int $shippingMethod = null;
    public static ?int $paymentMethod = null;
    public static ?int $shippingZone = null;
    public static null|array|\Illuminate\Database\Eloquent\Collection $depositPaymentMethods = null;
    public static ?int $depositPaymentMethod = null;

    public static ?string $cartType = 'default';
    public static null|array|Collection $cartItems = [];
    public static bool $initialized = false;

    public function initialize(?string $cartType = null)
    {
        $this->setCartType($cartType);

        if (static::$initialized) {
            return;
        }
        static::$initialized = true;
                ray()->measure();
        $this->updateData();
                ray()->measure();
    }

    public function updateData(): void
    {
        $this->setCartType();
        static::$calculateInclusiveTax = Customsetting::get('taxes_prices_include_taxes') ? true : false;

        $this->setCartItems();
        $this->setDiscountCode();
        $this->setTotal();
        $this->setTotalWithoutDiscount();
        $this->setDiscount();
        $this->setVatBaseInfoForCalculation();
        $this->setTax();
        $this->setTaxPercentages();
        $this->setSubtotal();
        $this->setShippingCosts();
        $this->setPaymentMethodCosts();
    }

    private function setCartItems(): void
    {
        if (static::$cartType) {
            Cart::instance(static::$cartType);
        }

        static::$cartItems = Cart::content();
    }

    private function setDiscountCode(bool $forceString = false): void
    {
        $code = $forceString ? static::$discountCodeString : (session('discountCode') ?: static::$discountCodeString);
        if (! $code) {
            static::$discountCode = null;

            return;
        }

        $discountCode = DiscountCode::usable()->isNotGlobalDiscount()->where('code', $code)->first();

        if (! $discountCode || ! $discountCode->isValidForCart(cartType: static::$cartType)) {
            session(['discountCode' => '']);
            static::$discountCode = null;
            static::$discountCodeString = null;
        }

        static::$discountCode = $discountCode;
        static::$discountCodeString = $discountCode->code ?? null;
        session(['discountCode' => static::$discountCodeString]);
    }

    private function setVatBaseInfoForCalculation(): void
    {
        $tax = 0;
        $taxWithoutDiscount = 0;
        $vatRates = 0;
        $vatRatesCount = 0;
        $totalAmountForVats = [];
        $totalPriceForProducts = 0;

        foreach (static::$cartItems as $cartItem) {
            if ($cartItem->model || ($cartItem->options['customProduct'] ?? false)) {
                $cartProducts = [$cartItem->model ?? $cartItem];
                if ($cartItem->model && $cartItem->model->is_bundle && $cartItem->model->use_bundle_product_price) {
                    $cartProducts = $cartItem->model->bundleProducts;
                }

                foreach ($cartProducts as $cartProduct) {
                    if (static::$discountCode && static::$discountCode->type == 'percentage') {
                        $price = Product::getShoppingCartItemPrice($cartItem, static::$discountCode);
                        $priceWithoutDiscount = Product::getShoppingCartItemPrice($cartItem);
                    } else {
                        $price = Product::getShoppingCartItemPrice($cartItem);
                        $priceWithoutDiscount = $price;
                    }

                    $totalPriceForProducts += $price;

                    if (static::$calculateInclusiveTax) {
                        $price = $price / (100 + ($cartProduct->options['vat_rate'] ?? $cartProduct->vat_rate)) * ($cartProduct->options['vat_rate'] ?? $cartProduct->vat_rate);
                        $priceWithoutDiscount = $priceWithoutDiscount / (100 + ($cartProduct->options['vat_rate'] ?? $cartProduct->vat_rate)) * ($cartProduct->options['vat_rate'] ?? $cartProduct->vat_rate);
                    } else {
                        $price = $price / 100 * ($cartProduct->options['vat_rate'] ?? $cartProduct->vat_rate);
                        $priceWithoutDiscount = $priceWithoutDiscount / 100 * ($cartProduct->options['vat_rate'] ?? $cartProduct->vat_rate);
                    }

                    $tax += $price;
                    $taxWithoutDiscount += $priceWithoutDiscount;
                    if (($cartProduct->options['vat_rate'] ?? $cartProduct->vat_rate) > 0) {
                        if (! isset($totalAmountForVats[number_format(($cartProduct->options['vat_rate'] ?? $cartProduct->vat_rate), 0)])) {
                            $totalAmountForVats[number_format(($cartProduct->options['vat_rate'] ?? $cartProduct->vat_rate), 0)] = 0;
                        }
                        if (static::$discountCode && static::$discountCode->type == 'percentage') {
                            $totalCartItemAmount = Product::getShoppingCartItemPrice($cartItem, static::$discountCode);
                            if (! static::$calculateInclusiveTax) {
                                $totalCartItemAmount = $totalCartItemAmount / 100 * (100 + ($cartProduct->options['vat_rate'] ?? $cartProduct->vat_rate));
                            }

                            $totalAmountForVats[number_format(($cartProduct->options['vat_rate'] ?? $cartProduct->vat_rate), 0)] += $totalCartItemAmount;
                        } else {
                            $totalCartItemAmount = Product::getShoppingCartItemPrice($cartItem);
                            if (! static::$calculateInclusiveTax) {
                                $totalCartItemAmount = $totalCartItemAmount / 100 * (100 + ($cartProduct->options['vat_rate'] ?? $cartProduct->vat_rate));
                            }

                            $totalAmountForVats[number_format(($cartProduct->options['vat_rate'] ?? $cartProduct->vat_rate), 0)] += $totalCartItemAmount;
                        }
                        $vatRates += ($cartProduct->options['vat_rate'] ?? $cartProduct->vat_rate) * $cartItem->qty;
                        $vatRatesCount += $cartItem->qty;
                    }
                }
            } else {
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

        static::$totalAmountForVats = $totalAmountForVats;
        static::$totalVatPerPercentage = $totalVatPerPercentage;
        static::$vatPercentageOfTotals = $vatPercentageOfTotals;
        static::$vatRates = $vatRates;
        static::$vatRatesCount = $vatRatesCount;
        static::$tax = $tax;
        static::$taxWithoutDiscount = $taxWithoutDiscount;
    }

    public function getVatRateForShippingMethod(): int
    {
        if (static::$shippingMethod && static::$vatRatesCount) {
            return round(static::$vatRates / static::$vatRatesCount, 2);
        }

        return 0;
    }

    public function getVatForShippingMethod(?int $vatRate = null): float
    {
        if (! static::$shippingMethod) {
            return 0;
        }

        if (! $vatRate) {
            $vatRate = self::getVatRateForShippingMethod();
        }

        $tax = 0;

        if (static::$shippingMethod) {
            if (static::$calculateInclusiveTax) {
                $tax += ShippingMethod::find(static::$shippingMethod)->costsForCart(static::$shippingZone) / (100 + $vatRate) * $vatRate;
            } else {
                $tax += ShippingMethod::find(static::$shippingMethod)->costsForCart(static::$shippingZone) / 100 * $vatRate;
            }
        }

        return round($tax, 2);
    }

    public function getVatForPaymentMethod(): float
    {
        $tax = 0;

        if (static::$paymentMethod) {
            foreach (ShoppingCart::getPaymentMethods() as $paymentMethod) {
                if ($paymentMethod['id'] == static::$paymentMethod && $paymentMethod['extra_costs'] > 0) {
                    if (static::$calculateInclusiveTax) {
                        $tax = $tax + ($paymentMethod['extra_costs'] / 121 * 21);
                    } else {
                        $tax = $tax + ($paymentMethod['extra_costs'] / 100 * 21);
                    }
                }
            }
        }

        return round($tax, 2);
    }

    private function setTax(): void
    {
        $tax = static::$tax;
        $taxWithoutDiscount = static::$taxWithoutDiscount;

        if (static::$discountCode && static::$discountCode->type == 'amount') {
            if (static::$calculateInclusiveTax) {
                foreach (static::$vatPercentageOfTotals as $percentage => $vatPercentageOfTotal) {
                    if ($vatPercentageOfTotal) {
                        $tax -= ((static::$discountCode->discount_amount * ($vatPercentageOfTotal / 100)) / (100 + $percentage) * $percentage);
                    }
                }
            } else {
                foreach (static::$vatPercentageOfTotals as $percentage => $vatPercentageOfTotal) {
                    if ($vatPercentageOfTotal) {
                        $tax -= ((static::$discountCode->discount_amount * ($vatPercentageOfTotal / 100)) / 100 * $percentage);
                    }
                }
            }
        }

        $tax += static::getVatForShippingMethod();
        $taxWithoutDiscount += static::getVatForShippingMethod();
        $tax += static::getVatForPaymentMethod();
        $taxWithoutDiscount += static::getVatForPaymentMethod();

        if ($tax < 0) {
            $tax = 0;
        }
        if ($taxWithoutDiscount < 0) {
            $taxWithoutDiscount = 0;
        }

        static::$tax = number_format($tax, 2, '.', '');
        static::$taxWithoutDiscount = number_format($taxWithoutDiscount, 2, '.', '');
    }

    private function setSubtotal(): void
    {
        $total = static::$totalWithoutDiscount;

        if (! self::$calculateInclusiveTax) {
            $total -= static::$taxWithoutDiscount;

            if (static::$shippingMethod) {
                $total -= static::$shippingMethod->costsForCart(static::$shippingZone);
            }

            if (static::$paymentMethod) {
                foreach (ShoppingCart::getPaymentMethods() as $paymentMethod) {
                    if ($paymentMethod['id'] == static::$paymentMethod->id) {
                        $total -= $paymentMethod['extra_costs'];
                    }
                }
            }
        }

        if ($total < 0) {
            $total = 0.01;
        }

        static::$subtotal = number_format($total, 2, '.', '');
    }

    private function setTotalWithoutDiscount(): void
    {
        static::$totalWithoutDiscount = static::$total;
    }

    private function setShippingCosts(): void
    {
        $shippingCosts = 0;

        if (static::$shippingMethod) {
            $shippingCosts = ShippingMethod::find(static::$shippingMethod)->costsForCart(static::$shippingZone);
        }

        static::$shippingCosts = $shippingCosts;
    }

    private function setPaymentMethodCosts(): void
    {
        $paymentCosts = 0;

        $isPostPayMethod = false;
        if (static::$paymentMethod) {
            foreach (ShoppingCart::getPaymentMethods(total: static::$total) as $paymentMethod) {
                if ($paymentMethod['id'] == static::$paymentMethod) {
                    $paymentCosts = $paymentMethod['extra_costs'];
                    $isPostPayMethod = $paymentMethod['postpay'];
                }
            }
        }

        static::$paymentCosts = $paymentCosts;
        static::$isPostPayMethod = $isPostPayMethod;
    }

    public function getIsPostpayPaymentMethod(): bool
    {
        return static::$isPostPayMethod;
    }

    private function setTotal(): void
    {
        $total = 0;

        foreach (static::$cartItems as $cartItem) {
            $total += $cartItem->model ? Product::getShoppingCartItemPrice($cartItem) : ($cartItem->price * $cartItem->qty);
        }

        if (! static::$calculateInclusiveTax) {
            $total += static::$tax;
        }

        if (static::$shippingMethod) {
            $total += ShippingMethod::find(static::$shippingMethod)->costsForCart(static::$shippingZone);
        }

        if (static::$paymentMethod) {
            foreach (ShoppingCart::getPaymentMethods() as $paymentMethod) {
                if ($paymentMethod['id'] == static::$paymentMethod) {
                    $total += $paymentMethod['extra_costs'];
                }
            }
        }

        if ($total < 0) {
            $total = 0.01;
        }

        static::$total = $total;
    }

    private function setDiscount(): void
    {
        $totalDiscount = 0;

        if (static::$discountCode) {
            if (static::$discountCode->type == 'percentage') {
                $itemsInCart = static::$cartItems;

                foreach ($itemsInCart as $item) {
                    $totalDiscount += Product::getShoppingCartItemPrice($item) - Product::getShoppingCartItemPrice($item, static::$discountCode);
                }
            } elseif (static::$discountCode->type == 'amount') {
                $totalDiscount = static::$discountCode->discount_amount;
            }

        }

        $total = static::$total;

        if ($totalDiscount > $total) {
            $totalDiscount = $total - 0.01;
        }

        static::$discount = number_format($totalDiscount, 2, '.', '');
        static::$total = number_format(static::$totalWithoutDiscount - $totalDiscount, 2, '.', '');
    }

    public function setShippingMethod(?int $shippingMethod = null): void
    {
        static::$shippingMethod = $shippingMethod;
    }

    public function setShippingZone(?int $shippingZone = null): void
    {
        static::$shippingZone = $shippingZone;
    }

    public function setPaymentMethod(?int $paymentMethod = null): void
    {
        static::$paymentMethod = $paymentMethod;
        $this->setDepositPaymentMethods();
    }

    public function setDepositPaymentMethod(?int $depositPaymentMethod = null): void
    {
        static::$depositPaymentMethod = $depositPaymentMethod;
    }

    public function setDepositPaymentMethods(): void
    {
        $paymentMethod = PaymentMethod::find(static::$paymentMethod);

        if ($paymentMethod && $paymentMethod->deposit_calculation_payment_method_ids) {
            $depositPaymentMethods = PaymentMethod::find($paymentMethod->deposit_calculation_payment_method_ids)->toArray();
            foreach ($depositPaymentMethods as &$depositPaymentMethod) {
                $depositPaymentMethod['full_image_path'] = $depositPaymentMethod['image'] ? Storage::disk('dashed')->url($depositPaymentMethod['image']) : '';
                $depositPaymentMethod['name'] = $depositPaymentMethod['name'][app()->getLocale()] ?? '';
                $depositPaymentMethod['additional_info'] = $depositPaymentMethod['additional_info'][app()->getLocale()] ?? '';
                $depositPaymentMethod['payment_instructions'] = $depositPaymentMethod['payment_instructions'][app()->getLocale()] ?? '';
            }
        }

        static::$depositPaymentMethods = $depositPaymentMethods ?? [];
        $this->setDepositAmount();
    }

    public function setDepositAmount(): void
    {
        $depositAmount = 0;

        if (static::$paymentMethod) {
            foreach (ShoppingCart::getPaymentMethods() as $paymentMethod) {
                if ($paymentMethod['id'] == static::$paymentMethod) {
                    if ($paymentMethod['deposit_calculation']) {
                        $paymentMethod['deposit_calculation'] = str_replace('{ORDER_TOTAL_MINUS_PAYMENT_COSTS}', static::$total, $paymentMethod['deposit_calculation']);
                        $paymentMethod['deposit_calculation'] = str_replace('{ORDER_TOTAL}', static::$total, $paymentMethod['deposit_calculation']);
                        $depositAmount = eval('return ' . $paymentMethod['deposit_calculation'] . ';');
                    }
                }
            }
        }

        static::$depositAmount = number_format($depositAmount, 2);
    }

    private function setTaxPercentages(): void
    {
        $totalVatPerPercentage = static::$totalVatPerPercentage;
        if (static::$discountCode && static::$discountCode->type == 'amount') {
            $discount = static::$discountCode->discount_amount;

            if (static::$calculateInclusiveTax) {
                foreach (static::$vatPercentageOfTotals as $percentage => $vatPercentageOfTotal) {
                    if ($vatPercentageOfTotal) {
                        foreach ($totalVatPerPercentage as $percentage => $value) {
                            $totalVatPerPercentage[$percentage] -= (($discount * ($vatPercentageOfTotal / 100)) / (100 + $percentage) * $percentage);
                        }
                    }
                }
            } else {
                foreach (static::$vatPercentageOfTotals as $percentage => $vatPercentageOfTotal) {
                    if ($vatPercentageOfTotal) {
                        foreach ($totalVatPerPercentage as $percentage => $value) {
                            $totalVatPerPercentage[$percentage] -= (($discount * ($vatPercentageOfTotal / 100)) / 100 * $percentage);
                        }
                    }
                }
            }
        }

        if (static::$shippingMethod) {
            foreach ($totalVatPerPercentage as $percentage => $value) {
                $result = $this->getVatForShippingMethod($percentage);
                $totalVatPerPercentage[$percentage] += $result;
            }
        }

        if (static::$paymentMethod) {
            $paymentVat = $this->getVatForPaymentMethod();
            isset($totalVatPerPercentage[21]) ? $totalVatPerPercentage[21] += $paymentVat : $totalVatPerPercentage[21] = $paymentVat;
        }

        foreach ($totalVatPerPercentage as $percentage => $value) {
            $totalVatPerPercentage[$percentage] = round($value, 2);
        }

        static::$taxPercentages = $totalVatPerPercentage;
    }

    public function getTotal(): float
    {
        return static::$total;
    }

    public function getTaxPercentages(): array
    {
        return static::$taxPercentages;
    }

    public function getDiscount(): float
    {
        return static::$discount;
    }

    public function getTax(): float
    {
        return static::$tax;
    }

    public function getTotalWithoutDiscount(): float
    {
        return static::$totalWithoutDiscount;
    }

    public function getSubtotal(): float
    {
        return static::$subtotal;
    }

    public function getShippingCosts(): float
    {
        return static::$shippingCosts;
    }

    public function getPaymentCosts(): float
    {
        return static::$paymentCosts;
    }

    public function getDepositAmount(): float
    {
        return static::$depositAmount;
    }

    public function getDepositPaymentMethods(): null|array|\Illuminate\Database\Eloquent\Collection
    {
        return static::$depositPaymentMethods;
    }

    public function getDepositPaymentMethod(): ?PaymentMethod
    {
        return static::$depositPaymentMethod;
    }

    public function getDiscountCode(): ?DiscountCode
    {
        return static::$discountCode;
    }

    public function getDiscountCodeString(): ?string
    {
        return static::$discountCodeString;
    }

    public function getCartItems(): Collection|null|array
    {
        return static::$cartItems;
    }

    public function removeInvalidItems($checkStock = true): bool
    {
        $cartChanged = false;
        $cartItems = static::$cartItems;
        $parentItemsToCheck = collect();

        // Loop through cart items
        foreach ($cartItems as $cartItem) {
            $cartItemDeleted = false;

            if (! $cartItem->model) {
                if ($cartItem->associatedModel) {
                    Cart::remove($cartItem->rowId);
                    $cartChanged = true;
                }

                continue;
            }

            $model = $cartItem->model;

            // Handle removed or unavailable products
            if ($model->trashed() || ! $model->publicShowable()) {
                Cart::remove($cartItem->rowId);
                EcommerceActionLog::createLog('remove_from_cart', $cartItem->qty, productId: $model->id);
                $cartItemDeleted = true;
                $cartChanged = true;

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
                    EcommerceActionLog::createLog('remove_from_cart', $cartItem->qty - $newStock, productId: $model->id);
                    Notification::make()
                        ->body(Translation::get('product-less-stock', 'cart', ':product: is verlaagd in je winkelwagen omdat er maar :stock: voorraad is.', 'text', [
                            'product' => $model->name,
                            'stock' => $newStock,
                        ]))
                        ->danger()
                        ->send();
                    $cartChanged = true;
                } else {
                    EcommerceActionLog::createLog('remove_from_cart', $cartItem->qty, productId: $model->id);
                    Cart::remove($cartItem->rowId);
                    $cartItemDeleted = true;

                    Notification::make()
                        ->body(Translation::get('product-out-of-stock', 'cart', ':product: is uit je winkelwagen gehaald omdat er geen voorraad meer is.', 'text', [
                            'product' => $model->name,
                        ]))
                        ->danger()
                        ->send();
                    $cartChanged = true;
                }
            }

            // Handle purchase limits
            if (! $cartItemDeleted && $model->limit_purchases_per_customer && $cartItem->qty > $model->limit_purchases_per_customer_limit) {
                EcommerceActionLog::createLog('remove_from_cart', $cartItem->qty - $model->limit_purchases_per_customer_limit, productId: $model->id);
                Cart::update($cartItem->rowId, $model->limit_purchases_per_customer_limit);
                $cartChanged = true;
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
                        $cartChanged = true;
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
                            $cartChanged = true;
                        }
                        $price = $volumeDiscount->getPrice($price);
                    }
                }

                if ($cartItem->price != $price) {
                    Cart::update($cartItem->rowId, [
                        'price' => $price,
                    ]);
                    $cartChanged = true;
                }
            }
        }

        // Check parent product group stock
        $parentItemsToCheck->unique()->each(function ($parentId) {
            $parentProduct = Product::find($parentId);
            if (! $parentProduct) {
                return;
            }

            $cartItems = static::$cartItems->filter(function ($cartItem) use ($parentId) {
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

                EcommerceActionLog::createLog('remove_from_cart', $currentAmount, productId: $parentProduct->id);

                $cartItems->each(function ($cartItem) use ($maxStock) {
                    Cart::remove($cartItem->rowId);
                });
                $cartChanged = true;
            }
        });

        if ($cartChanged) {
            $this->updateData();
        }

        return $cartChanged;
    }

    public function emptyCart(): void
    {
        session(['discountCode' => '']);
        Cart::destroy();
    }

    public function applyDiscountCode(?string $code = null): array
    {
        if (! $code) {
            session(['discountCode' => '']);
            $this->updateData();

            return [
                'status' => 'danger',
                'message' => Translation::get('discount-code-not-valid', static::$cartType, 'The discount code is not valid'),
            ];
        }


        static::$discountCodeString = $code;
        $this->setDiscountCode(true, static::$cartType);
        //        ray(cartHelper()->getCartType());
        $this->updateData();

        if (! static::$discountCode) {
            return [
                'status' => 'danger',
                'message' => Translation::get('discount-code-not-valid', static::$cartType, 'The discount code is not valid'),
            ];
        }

        return [
            'status' => 'success',
            'message' => Translation::get('discount-code-applied', static::$cartType, 'The discount code has been applied and discount has been calculated'),
        ];
    }

    public function changeQuantity(string $rowId, int $quantity): array
    {
        $dispatch = [];
        if (! $quantity) {
            if (ShoppingCart::hasCartitemByRowId($rowId)) {
                $cartItem = \Gloudemans\Shoppingcart\Facades\Cart::get($rowId);
                \Gloudemans\Shoppingcart\Facades\Cart::remove($rowId);

                EcommerceActionLog::createLog('remove_from_cart', $cartItem->qty, productId: $cartItem->model->id);

                $this->updateData();

                $cartTotal = static::$total;

                $dispatch = [
                    'event' => 'productRemovedFromCart',
                    'data' => [
                        'product' => $cartItem->model,
                        'productName' => $cartItem->model->name,
                        'quantity' => $quantity,
                        'price' => number_format($cartItem->model->price, 2, '.', ''),
                        'cartTotal' => number_format($cartTotal, 2, '.', ''),
                        'category' => $cartItem->model->productCategories->first()?->name ?? null,
                        'tiktokItems' => TikTokHelper::getShoppingCartItems($cartTotal),
                    ],
                ];
            }

            return [
                'status' => 'success',
                'message' => Translation::get('product-removed-from-cart', static::$cartType, 'The product has been removed from your cart'),
                'dispatch' => $dispatch,
            ];
        } else {
            if (ShoppingCart::hasCartitemByRowId($rowId)) {
                $cartItem = \Gloudemans\Shoppingcart\Facades\Cart::get($rowId);
                if ($cartItem->qty > $quantity) {
                    EcommerceActionLog::createLog('remove_from_cart', ($cartItem->qty - $quantity), productId: $cartItem->model->id);
                } else {
                    EcommerceActionLog::createLog('add_to_cart', ($quantity - $cartItem->qty), productId: $cartItem->model->id);
                }
                \Gloudemans\Shoppingcart\Facades\Cart::update($rowId, ($quantity));
            }

            return [
                'status' => 'success',
                'message' => Translation::get('product-updated-to-cart', static::$cartType, 'The product has been updated to your cart'),
                'dispatch' => $dispatch,
            ];
        }
    }

    public function setCartType(?string $cartType = null): void
    {
        if ($cartType) {
            static::$cartType = $cartType;
        } elseif (! static::$cartType) {
            static::$cartType = 'default';
        }

        Cart::instance(static::$cartType);
    }

    public function getCartType(): string
    {
        return static::$cartType;
    }
}
