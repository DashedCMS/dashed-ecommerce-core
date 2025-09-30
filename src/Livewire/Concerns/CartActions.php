<?php

namespace Dashed\DashedEcommerceCore\Livewire\Concerns;

use Illuminate\Support\Str;
use Dashed\DashedCore\Classes\Sites;
use Illuminate\Support\Facades\Storage;
use Filament\Notifications\Notification;
use Gloudemans\Shoppingcart\Facades\Cart;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedTranslations\Models\Translation;
use Dashed\DashedEcommerceCore\Models\DiscountCode;
use Dashed\DashedEcommerceCore\Classes\ShoppingCart;
use Dashed\DashedEcommerceCore\Classes\TikTokHelper;
use Dashed\DashedEcommerceCore\Models\EcommerceActionLog;
use Dashed\DashedEcommerceCore\Models\ProductExtraOption;

trait CartActions
{
    public $suggestedProducts = [];
    public $crossSellProducts = [];
    public ?array $extras = [];
    public ?array $hiddenOptions = [];
    public string|int $quantity = 1;
    public array $files = [];
    public string $cartType = 'default';

    public function checkCart(?string $status = null, ?string $message = null)
    {
        if ($status) {
            if ($status == 'error') {
                $status = 'danger';
            }

            Notification::make()
                ->$status()
                ->title($message)
                ->send();
        }

        ShoppingCart::removeInvalidItems($this->cartType);

        $this->dispatch('refreshCart');
    }

    public function changeQuantity(string $rowId, int $quantity)
    {
        ShoppingCart::setInstance($this->cartType);
        if (! $quantity) {
            if (ShoppingCart::hasCartitemByRowId($rowId)) {
                $cartItem = \Gloudemans\Shoppingcart\Facades\Cart::get($rowId);
                \Gloudemans\Shoppingcart\Facades\Cart::remove($rowId);

                EcommerceActionLog::createLog('remove_from_cart', $cartItem->qty, productId: $cartItem->model->id);

                $cartTotal = ShoppingCart::total(false, shippingZoneId: $this->shippingMethod->shipping_zone_id ?? null);
                $this->dispatch('productRemovedFromCart', [
                    'product' => $cartItem->model,
                    'productName' => $cartItem->model->name,
                    'quantity' => $quantity,
                    'price' => number_format($cartItem->model->price, 2, '.', ''),
                    'cartTotal' => number_format($cartTotal, 2, '.', ''),
                    'category' => $cartItem->model->productCategories->first()?->name ?? null,
                    'tiktokItems' => TikTokHelper::getShoppingCartItems($cartTotal),
                ]);
            }

            $this->checkCart('success', Translation::get('product-removed-from-cart', $this->cartType, 'The product has been removed from your cart'));
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

            $this->checkCart('success', Translation::get('product-updated-to-cart', $this->cartType, 'The product has been updated to your cart'));
        }

        $this->fillPrices();
    }

    public function applyDiscountCode()
    {
        ShoppingCart::setInstance($this->cartType);

        if (! $this->discountCode) {
            session(['discountCode' => '']);
            $this->discountCode = '';
            $this->discount = 0;
            $this->fillPrices();

            return $this->checkCart('danger', Translation::get('discount-code-not-valid', $this->cartType, 'The discount code is not valid'));
        }

        $discountCode = DiscountCode::usable()->where('code', $this->discountCode)->first();

        if (! $discountCode || ! $discountCode->isValidForCart()) {
            session(['discountCode' => '']);
            $this->discountCode = '';
            $this->fillPrices();

            return $this->checkCart('danger', Translation::get('discount-code-not-valid', $this->cartType, 'The discount code is not valid'));
        }

        session(['discountCode' => $discountCode->code]);
        $this->fillPrices();

        return $this->checkCart('success', Translation::get('discount-code-applied', $this->cartType, 'The discount code has been applied and discount has been calculated'));
    }

    public function retrievePaymentMethods(): void
    {
    }

    public function retrieveShippingMethods(): void
    {
    }

    public function fillPrices(): void
    {
        $this->retrievePaymentMethods();
        $this->retrieveShippingMethods();
        ShoppingCart::setInstance($this->cartType);

        $checkoutData = ShoppingCart::getCheckoutData($this->shippingMethod, $this->paymentMethod, shippingZoneId: $this->shippingMethods->find($this->shippingMethod)->shipping_zone_id ?? null);
        $this->subtotal = $checkoutData['subTotal'];
        $this->discount = $checkoutData['discount'];
        $this->tax = $checkoutData['btw'];
        $this->total = $checkoutData['total'];
        $this->shippingCosts = $checkoutData['shippingCosts'];
        $this->paymentCosts = $checkoutData['paymentCosts'];
        $this->depositAmount = $checkoutData['depositAmount'];
        $this->depositPaymentMethods = $checkoutData['depositPaymentMethods'];
        $this->postpayPaymentMethod = $checkoutData['postpayPaymentMethod'];
        $this->getSuggestedProducts();
        $this->dispatch('filledPrices');
    }

    public function getSuggestedProducts()
    {
        $this->suggestedProducts = ShoppingCart::getCrossSellAndSuggestedProducts();
        $this->crossSellProducts = ShoppingCart::getCrossSellProducts();
    }

    public function addToCart(?int $productId = null)
    {
        if ($productId) {
            $product = Product::find($productId);
        } else {
            $product = $this->product;
        }

        ShoppingCart::setInstance($this->cartType);

        if (! $product) {
            return $this->checkCart('danger', Translation::get('choose-a-product', $this->cartType, 'Please select a product'));
        }

        $cartUpdated = false;
        $productPrice = $product->currentPrice;
        $discountedProductPrice = $product->discountPrice;
        $options = [];
        foreach ($product->allProductExtras() as $extraKey => $productExtra) {
            $productExtraPrice = 0;
            if ((($this->extras[$extraKey]['value'] ?? false) || ($this->files[$productExtra->id] ?? false)) && $productExtra->price) {
                $productPrice += $productExtra->price;
                $productExtraPrice += $productExtra->price;
                $discountedProductPrice += $productExtra->price;
            }
            if ($productExtra->type == 'single' || $productExtra->type == 'imagePicker') {
                $productValue = $this->extras[$extraKey]['value'] ?? null;
                if ($productExtra->required && ! $productValue) {
                    return $this->checkCart('danger', Translation::get('select-option-for-product-extra', 'products', 'Select an option for :optionName:', 'text', [
                        'optionName' => $productExtra->name,
                    ]));
                }

                if ($productValue) {
                    if ($productValue === true) {
                        $productValue = $this->extras[$extraKey]['id'];
                    }

                    $productExtraOption = ProductExtraOption::find($productValue);
                    if ($productExtraOption->calculate_only_1_quantity) {
                        $productPrice += ($productExtraOption->price / $this->quantity);
                        $productExtraPrice += ($productExtraOption->price / $this->quantity);
                        $discountedProductPrice += ($productExtraOption->price / $this->quantity);
                    } else {
                        $productPrice += $productExtraOption->price;
                        $productExtraPrice += $productExtraOption->price;
                        $discountedProductPrice += $productExtraOption->price;
                    }
                    $options[$productExtraOption->id] = [
                        'name' => $productExtra->name,
                        'value' => $productExtraOption->value,
                        'price' => $productExtraPrice,
                    ];
                }
            } elseif ($productExtra->type == 'checkbox') {
                //As long as this only can have 1 option, this will work
                $productValue = $this->extras[$extraKey]['value'] ?? null;
                if ($productExtra->required && ! $productValue) {
                    return $this->checkCart('danger', Translation::get('select-checkbox-for-product-extra', 'products', 'Select the checkbox for :optionName:', 'text', [
                        'optionName' => $productExtra->name,
                    ]));
                }

                if ($productValue) {
                    $productExtraOption = ProductExtraOption::find($this->extras[$extraKey]['product_extra_options'][0]['id'] ?? null);
                    if ($productExtraOption->calculate_only_1_quantity) {
                        $productPrice += ($productExtraOption->price / $this->quantity);
                        $productExtraPrice += ($productExtraOption->price / $this->quantity);
                        $discountedProductPrice += ($productExtraOption->price / $this->quantity);
                    } else {
                        $productPrice += $productExtraOption->price;
                        $productExtraPrice += $productExtraOption->price;
                        $discountedProductPrice += $productExtraOption->price;
                    }
                    $options[$productExtraOption->id] = [
                        'name' => $productExtra->name,
                        'value' => $productExtraOption->value,
                        'price' => $productExtraPrice,
                    ];
                }
            } elseif ($productExtra->type == 'multiple') {
                $productValues = $this->extras[$extraKey]['value'] ?? null;
                if ($productExtra->required && ! $productValues) {
                    return $this->checkCart('danger', Translation::get('select-multiple-for-product-extra', 'products', 'Select at least 1 option for :optionName:', 'text', [
                        'optionName' => $productExtra->name,
                    ]));
                }

                if ($productValues) {
                    $customValues = [];
                    foreach ($productValues as $productValue) {
                        $productExtraOption = ProductExtraOption::find($productValue);
                        if ($productExtraOption) {
                            if ($productExtraOption->calculate_only_1_quantity) {
                                $productPrice += ($productExtraOption->price / $this->quantity);
                                $productExtraPrice += ($productExtraOption->price / $this->quantity);
                                $discountedProductPrice += ($productExtraOption->price / $this->quantity);
                            } else {
                                $productPrice += $productExtraOption->price;
                                $productExtraPrice += $productExtraOption->price;
                                $discountedProductPrice += $productExtraOption->price;
                            }
                            $options[$productExtraOption->id] = [
                                'name' => $productExtra->name,
                                'value' => $productExtraOption->value,
                                'price' => $productExtraPrice,
                            ];
                        } else {
                            $customValues[] = $productValue;
                        }
                    }

                    if ($customValues) {
                        $options['custom-value-' . rand(0, 1000000)] = [
                            'name' => $productExtra->name,
                            'value' => $customValues,
                            'price' => $productExtraPrice,
                        ];
                    }
                }
            } elseif ($productExtra->type == 'input') {
                $productValue = $this->extras[$extraKey]['value'] ?? null;
                if ($productExtra->required && ! $productValue) {
                    return $this->checkCart('danger', Translation::get('fill-option-for-product-extra', 'products', 'Fill the input field for :optionName:', 'text', [
                        'optionName' => $productExtra->name,
                    ]));
                }

                if ($productValue) {
                    if ($productValue === true) {
                        $productValue = $this->extras[$extraKey]['id'];
                    }
                    $options['product-extra-input-' . $productExtra->id] = [
                        'name' => $productExtra->name,
                        'value' => $productValue,
                        'price' => $productExtraPrice,
                    ];
                }
            } elseif ($productExtra->type == 'file') {
                $productValue = $this->files[$productExtra->id] ?? null;
                if (! $productValue) {
                    $productValue = $this->extras[$extraKey]['value'] ?? null;
                }

                if ($productExtra->required && ! ($productValue['value'] ?? null)) {
                    return $this->checkCart('danger', Translation::get('file-upload-option-for-product-extra', 'products', 'Upload an file for option :optionName:', 'text', [
                        'optionName' => $productExtra->name,
                    ]));
                }

                if ($productValue['value'] ?? false) {
                    if (Storage::disk('dashed')->exists($productValue['value'])) {
                        $path = $productValue['value'];
                        $value = str($path)->explode('/')->last();
                    } else {
                        $value = Str::uuid() . '-' . $productValue['value']->getClientOriginalName();
                        $path = $productValue['value']->storeAs('dashed/product-extras', $value, 'dashed');
                    }
                }

                if (($value ?? false) && ($path ?? false)) {
                    $options['product-extra-file-' . $productExtra->id] = [
                        'name' => $productExtra->name,
                        'value' => $value,
                        'path' => $path,
                        'price' => $productExtraPrice,
                    ];
                }
            } else {
                foreach ($productExtra->productExtraOptions as $option) {
                    $productOptionValue = $option['value'] ?? null;
                    //                    $productOptionValue = $request['product-extra-' . $productExtra->id . '-' . $option->id];
                    if ($productExtra->required && ! $productOptionValue) {
                        return $this->checkCart('danger', Translation::get('select-multiple-options-for-product-extra', 'products', 'Select one or more options for :optionName:', 'text', [
                            'optionName' => $productExtra->name,
                        ]));
                    }

                    if ($productOptionValue) {
                        if ($option->calculate_only_1_quantity) {
                            $productPrice = $productPrice + ($option->price / $this->quantity);
                            $productExtraPrice = $productPrice + ($option->price / $this->quantity);
                            $discountedProductPrice = $productPrice + ($option->price / $this->quantity);
                        } else {
                            $productPrice = $productPrice + $option->price;
                            $productExtraPrice = $productPrice + $option->price;
                            $discountedProductPrice = $productPrice + $option->price;
                        }
                        $options[$option->id] = [
                            'name' => $productExtra->name,
                            'value' => $option->value,
                            'price' => $productExtraPrice,
                        ];
                    }
                }
            }
        }

        $attributes['discountPrice'] = $discountedProductPrice;
        $attributes['originalPrice'] = $productPrice;
        $attributes['options'] = $options;
        $attributes['hiddenOptions'] = $this->hiddenOptions;

        $cartItems = ShoppingCart::cartItems($this->cartType);
        foreach ($cartItems as $cartItem) {
            if ($cartItem->model && $cartItem->model->id == $product->id && $attributes['options'] == $cartItem->options['options'] && $attributes['hiddenOptions'] == $cartItem->options['hiddenOptions']) {
                $newQuantity = $cartItem->qty + $this->quantity;

                if ($product->limit_purchases_per_customer && $newQuantity > $product->limit_purchases_per_customer_limit) {
                    Cart::update($cartItem->rowId, $product->limit_purchases_per_customer_limit);
                    EcommerceActionLog::createLog('add_to_cart', $product->limit_purchases_per_customer_limit - $cartItem->qty, productId: $product->id);

                    return $this->checkCart('danger', Translation::get('product-only-x-purchase-per-customer', $this->cartType, 'You can only purchase :quantity: of this product', 'text', [
                        'quantity' => $product->limit_purchases_per_customer_limit,
                    ]));
                }

                Cart::update($cartItem->rowId, $newQuantity);
                EcommerceActionLog::createLog('add_to_cart', $newQuantity - $cartItem->qty, productId: $product->id);
                $cartUpdated = true;
            }
        }

        if (! $cartUpdated) {
            if ($product->limit_purchases_per_customer && $this->quantity > $product->limit_purchases_per_customer_limit) {
                Cart::add($product->id, $product->name, $product->limit_purchases_per_customer_limit, $productPrice, $attributes)
                    ->associate(Product::class);
                EcommerceActionLog::createLog('add_to_cart', $product->limit_purchases_per_customer_limit, productId: $product->id);

                return $this->checkCart('danger', Translation::get('product-only-x-purchase-per-customer', $this->cartType, 'You can only purchase :quantity: of this product', 'text', [
                    'quantity' => $product->limit_purchases_per_customer_limit,
                ]));
            }

            Cart::add($product->id, $product->name, $this->quantity, $productPrice, $attributes)
                ->associate(Product::class);
            EcommerceActionLog::createLog('add_to_cart', $this->quantity, productId: $product->id);
        }

        $quantity = $this->quantity;
        $this->quantity = 1;
        $this->hiddenOptions = [];

        $redirectChoice = Customsetting::get('add_to_cart_redirect_to', Sites::getActive(), 'same');

        $cartTotal = ShoppingCart::total(false, shippingZoneId: $this->shippingMethod->shipping_zone_id ?? null);

        $this->dispatch('productAddedToCart', [
            'product' => $product,
            'productName' => $product->name,
            'quantity' => $quantity,
            'price' => number_format($productPrice, 2, '.', ''),
            'options' => $options,
            'cartTotal' => number_format($cartTotal, 2, '.', ''),
            'category' => $product->productCategories->first()?->name ?? null,
            'tiktokItems' => TikTokHelper::getShoppingCartItems($cartTotal),
        ]);

        session(['lastAddedProductInCart' => $product]);
        $this->fillPrices();

        if ($redirectChoice == 'same') {
            return $this->checkCart('success', Translation::get('product-added-to-cart', $this->cartType, 'The product has been added to your cart'));
        } elseif ($redirectChoice == 'cart') {
            $this->checkCart();

            Notification::make()
                ->success()
                ->title(Translation::get('product-added-to-cart', $this->cartType, 'The product has been added to your cart'))
                ->send();

            return redirect(ShoppingCart::getCartUrl());
        } elseif ($redirectChoice == 'checkout') {
            $this->checkCart();

            Notification::make()
                ->success()
                ->title(Translation::get('product-added-to-cart', $this->cartType, 'The product has been added to your cart'))
                ->send();

            return redirect(ShoppingCart::getCheckoutUrl());
        }
    }
}
