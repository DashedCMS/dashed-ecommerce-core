<?php

namespace Dashed\DashedEcommerceCore\Livewire\Concerns;

use Dashed\DashedCore\Classes\Sites;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Models\ProductExtraOption;
use Filament\Notifications\Notification;
use Dashed\DashedTranslations\Models\Translation;
use Dashed\DashedEcommerceCore\Models\DiscountCode;
use Dashed\DashedEcommerceCore\Classes\ShoppingCart;
use Gloudemans\Shoppingcart\Facades\Cart;

trait CartActions
{
    public function checkCart(?string $status = null, ?string $message = null)
    {
        if ($status) {
            if($status == 'error') {
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
                \Gloudemans\Shoppingcart\Facades\Cart::remove($rowId);
            }

            $this->checkCart('success', Translation::get('product-removed-from-cart', 'cart', 'The product has been removed from your cart'));
        } else {
            if (ShoppingCart::hasCartitemByRowId($rowId)) {
                $cartItem = \Gloudemans\Shoppingcart\Facades\Cart::get($rowId);
                \Gloudemans\Shoppingcart\Facades\Cart::update($rowId, ($quantity));
            }

            $this->checkCart('success', Translation::get('product-updated-to-cart', 'cart', 'The product has been updated to your cart'));
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

            return $this->checkCart('danger', Translation::get('discount-code-not-valid', 'cart', 'The discount code is not valid'));
        }

        $discountCode = DiscountCode::usable()->where('code', $this->discountCode)->first();

        if (! $discountCode || ! $discountCode->isValidForCart()) {
            session(['discountCode' => '']);
            $this->discountCode = '';
            $this->fillPrices();

            return $this->checkCart('danger', Translation::get('discount-code-not-valid', 'cart', 'The discount code is not valid'));
        }

        session(['discountCode' => $discountCode->code]);
        $this->fillPrices();

        return $this->checkCart('success', Translation::get('discount-code-applied', 'cart', 'The discount code has been applied and discount has been calculated'));
    }

    public function fillPrices()
    {
        ShoppingCart::setInstance($this->cartType);

        $checkoutData = ShoppingCart::getCheckoutData($this->shippingMethod, $this->paymentMethod);
        $this->subtotal = $checkoutData['subTotal'];
        $this->discount = $checkoutData['discount'];
        $this->tax = $checkoutData['btw'];
        $this->total = $checkoutData['total'];
        $this->shippingCosts = $checkoutData['shippingCosts'];
        $this->paymentCosts = $checkoutData['paymentCosts'];
        $this->depositAmount = $checkoutData['depositAmount'];
        $this->depositPaymentMethods = $checkoutData['depositPaymentMethods'];
        $this->postpayPaymentMethod = $checkoutData['postpayPaymentMethod'];
    }

    public function setQuantity(int $quantity)
    {
        $this->quantity = $quantity;
    }

    public function updatedQuantity()
    {
        if (! $this->quantity) {
            $this->quantity = 1;
        } elseif ($this->quantity < 1) {
            $this->quantity = 1;
        } elseif ($this->quantity > $this->product->stock()) {
            $this->quantity = $this->product->stock();
        }
    }

    public function checkFilters()
    {
        foreach ($this->filters as $filter) {
            if ($filter['active'] && $filter['defaultActive'] != $filter['active']) {
                foreach ($filter['values'] as $filterValue) {
                    if ($filterValue['value'] == $filter['active']) {
                        return redirect($filterValue['url']);
                    }
                }
            }
        }
    }

    public function updated()
    {
        $productPrice = $this->product->currentPrice;
        foreach ($this->productExtras as $extraKey => $productExtra) {
            if ($productExtra->type == 'single') {
                $productValue = $this->extras[$extraKey]['value'] ?? null;

                if ($productValue) {
                    $productExtraOption = ProductExtraOption::find($productValue);
                    if ($productExtraOption->calculate_only_1_quantity) {
                        $productPrice += ($productExtraOption->price / $this->quantity);
                    } else {
                        $productPrice += $productExtraOption->price;
                    }
                }
            } elseif ($productExtra->type == 'checkbox') {
                $productValue = $this->extras[$extraKey]['value'] ?? null;
                if ($productValue) {
                    $productExtraOption = ProductExtraOption::find($productValue);
                    if ($productExtraOption->calculate_only_1_quantity) {
                        $productPrice += ($productExtraOption->price / $this->quantity);
                    } else {
                        $productPrice += $productExtraOption->price;
                    }
                }
            } else {
                foreach ($productExtra->productExtraOptions as $option) {
                    $productOptionValue = $option['value'] ?? null;
                    if ($productOptionValue) {
                        if ($option->calculate_only_1_quantity) {
                            $productPrice = $productPrice + ($option->price / $this->quantity);
                        } else {
                            $productPrice = $productPrice + $option->price;
                        }
                    }
                }
            }
        }

        $this->price = $productPrice;
    }

    public function addToCart()
    {
        ShoppingCart::setInstance($this->cartType);
        $cartUpdated = false;
        $productPrice = $this->product->currentPrice;
        $options = [];
        foreach ($this->productExtras as $extraKey => $productExtra) {
            if ($productExtra->type == 'single') {
                $productValue = $this->extras[$extraKey]['value'] ?? null;
                if ($productExtra->required && ! $productValue) {
                    return $this->checkCart('danger', Translation::get('select-option-for-product-extra', 'products', 'Select an option for :optionName:', 'text', [
                        'optionName' => $productExtra->name,
                    ]));
                }

                if ($productValue) {
                    $productExtraOption = ProductExtraOption::find($productValue);
                    $options[$productExtraOption->id] = [
                        'name' => $productExtra->name,
                        'value' => $productExtraOption->value,
                    ];
                    if ($productExtraOption->calculate_only_1_quantity) {
                        $productPrice += ($productExtraOption->price / $this->quantity);
                    } else {
                        $productPrice += $productExtraOption->price;
                    }
                }
            } elseif ($productExtra->type == 'checkbox') {
                $productValue = $this->extras[$extraKey]['value'] ?? null;
                if ($productExtra->required && ! $productValue) {
                    return $this->checkCart('danger', Translation::get('select-checkbox-for-product-extra', 'products', 'Select the checkbox for :optionName:', 'text', [
                        'optionName' => $productExtra->name,
                    ]));
                }

                if ($productValue) {
                    $productExtraOption = ProductExtraOption::find($productValue);
                    $options[$productExtraOption->id] = [
                        'name' => $productExtra->name,
                        'value' => $productExtraOption->value,
                    ];
                    if ($productExtraOption->calculate_only_1_quantity) {
                        $productPrice += ($productExtraOption->price / $this->quantity);
                    } else {
                        $productPrice += $productExtraOption->price;
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
                    $options['product-extra-input-' . $productExtra->id] = [
                        'name' => $productExtra->name,
                        'value' => $productValue,
                    ];
                }
            } elseif ($productExtra->type == 'file') {
                $productValue = $this->files[$productExtra->id] ?? null;
                if ($productExtra->required && ! $productValue) {
                    return $this->checkCart('danger', Translation::get('file-upload-option-for-product-extra', 'products', 'Upload an file for option :optionName:', 'text', [
                        'optionName' => $productExtra->name,
                    ]));
                }

                $value = $productValue['value']->getClientOriginalName();
                $path = $productValue['value']->storeAs('dashed/product-extras', $value, 'dashed');
                if ($value && $path) {
                    $options['product-extra-file-' . $productExtra->id] = [
                        'name' => $productExtra->name,
                        'value' => $value,
                        'path' => $path,
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
                        $options[$option->id] = [
                            'name' => $productExtra->name,
                            'value' => $option->value,
                        ];
                        if ($option->calculate_only_1_quantity) {
                            $productPrice = $productPrice + ($option->price / $this->quantity);
                        } else {
                            $productPrice = $productPrice + $option->price;
                        }
                    }
                }
            }
        }

        $cartItems = ShoppingCart::cartItems($this->cartType);
        foreach ($cartItems as $cartItem) {
            if ($cartItem->model->id == $this->product->id && $options == $cartItem->options) {
                $newQuantity = $cartItem->qty + $this->quantity;

                if ($this->product->limit_purchases_per_customer && $newQuantity > $this->product->limit_purchases_per_customer_limit) {
                    Cart::update($cartItem->rowId, $this->product->limit_purchases_per_customer_limit);

                    return $this->checkCart('danger', Translation::get('product-only-x-purchase-per-customer', 'cart', 'You can only purchase :quantity: of this product', 'text', [
                        'quantity' => $this->product->limit_purchases_per_customer_limit,
                    ]));
                }

                Cart::update($cartItem->rowId, $newQuantity);
                $cartUpdated = true;
            }
        }

        if (! $cartUpdated) {
            if ($this->product->limit_purchases_per_customer && $this->quantity > $this->product->limit_purchases_per_customer_limit) {
                Cart::add($this->product->id, $this->product->name, $this->product->limit_purchases_per_customer_limit, $productPrice, $options)->associate(Product::class);

                return $this->checkCart('danger', Translation::get('product-only-x-purchase-per-customer', 'cart', 'You can only purchase :quantity: of this product', 'text', [
                    'quantity' => $this->product->limit_purchases_per_customer_limit,
                ]));
            }

            Cart::add($this->product->id, $this->product->name, $this->quantity, $productPrice, $options)->associate(Product::class);
        }

        $this->quantity = 1;

        $redirectChoice = Customsetting::get('add_to_cart_redirect_to', Sites::getActive(), 'same');
        if ($redirectChoice == 'same') {
            return $this->checkCart('success', Translation::get('product-added-to-cart', 'cart', 'The product has been added to your cart'));
        } elseif ($redirectChoice == 'cart') {
            $this->checkCart();

            Notification::make()
                ->success()
                ->title(Translation::get('product-added-to-cart', 'cart', 'The product has been added to your cart'))
                ->send();

            return redirect(ShoppingCart::getCartUrl());
        } elseif ($redirectChoice == 'checkout') {
            $this->checkCart();

            Notification::make()
                ->success()
                ->title(Translation::get('product-added-to-cart', 'cart', 'The product has been added to your cart'))
                ->send();

            return redirect(ShoppingCart::getCheckoutUrl());
        }
    }
}
