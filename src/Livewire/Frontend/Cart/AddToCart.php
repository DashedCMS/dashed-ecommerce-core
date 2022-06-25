<?php

namespace Qubiqx\QcommerceEcommerceCore\Livewire\Frontend\Cart;

use Livewire\Component;
use Qubiqx\QcommerceCore\Classes\Sites;
use Gloudemans\Shoppingcart\Facades\Cart;
use Qubiqx\QcommerceCore\Models\Customsetting;
use Qubiqx\QcommerceEcommerceCore\Models\Product;
use Qubiqx\QcommerceTranslations\Models\Translation;
use Qubiqx\QcommerceEcommerceCore\Classes\ShoppingCart;
use Qubiqx\QcommerceEcommerceCore\Models\ProductExtraOption;
use Qubiqx\QcommerceEcommerceCore\Livewire\Concerns\CartActions;

class AddToCart extends Component
{
    use CartActions;

    public Product $product;
    public int $quantity = 1;

    public function mount(Product $product)
    {
        $this->product = $product;
    }

    public function addToCart()
    {
        $cartItems = ShoppingCart::cartItems();
        $cartUpdated = false;
        $productPrice = $this->product->currentPrice;
        $options = [];
        foreach ($this->product->allProductExtras() as $productExtra) {
            if ($productExtra->type == 'single') {
                $productValue = $request['product-extra-' . $productExtra->id];
                if ($productExtra->required && ! $productValue) {
                    ShoppingCart::removeInvalidItems();

                    return $this->checkCart('error', Translation::get('not-all-required-options-chosen', 'cart', 'Not all extra`s have a selected option.'));
                }

                if ($productValue) {
                    $productExtraOption = ProductExtraOption::find($productValue);
                    $options[$productExtraOption->id] = [
                        'name' => $productExtra->name,
                        'value' => $productExtraOption->value,
                    ];
                    if ($productExtraOption->calculate_only_1_quantity) {
                        $productPrice += ($productExtraOption->price / $quantity);
                    } else {
                        $productPrice += $productExtraOption->price;
                    }
                }
            } else {
                foreach ($productExtra->productExtraOptions as $option) {
                    $productOptionValue = $request['product-extra-' . $productExtra->id . '-' . $option->id];
                    if ($productExtra->required && ! $productOptionValue) {
                        ShoppingCart::removeInvalidItems();

                        return redirect()->back()->with('error', Translation::get('not-all-required-options-chosen', 'cart', 'Not all extra`s have a selected option.'))->withInput();
                    }

                    if ($productOptionValue) {
                        $options[$option->id] = [
                            'name' => $productExtra->name,
                            'value' => $option->value,
                        ];
                        if ($option->calculate_only_1_quantity) {
                            $productPrice = $productPrice + ($option->price / $quantity);
                        } else {
                            $productPrice = $productPrice + $option->price;
                        }
                    }
                }
            }
        }

        foreach ($cartItems as $cartItem) {
            //Todo: the comparison for options does not work
            if ($cartItem->model->id == $product->id && $options == $cartItem->options) {
                $newQuantity = $cartItem->qty + $quantity;

                if ($product->limit_purchases_per_customer && $newQuantity > $product->limit_purchases_per_customer_limit) {
                    Cart::update($cartItem->rowId, $product->limit_purchases_per_customer_limit);

                    ShoppingCart::removeInvalidItems();

                    return redirect()->back()->with('error', Translation::get('product-only-x-purchase-per-customer', 'cart', 'You can only purchase :quantity: of this product', 'text', [
                        'quantity' => $product->limit_purchases_per_customer_limit,
                    ]))->withInput();
                }

                Cart::update($cartItem->rowId, $newQuantity);
                $cartUpdated = true;
            }
        }

        if (! $cartUpdated) {
            if ($product->limit_purchases_per_customer && $quantity > $product->limit_purchases_per_customer_limit) {
                Cart::add($product->id, $product->name, $product->limit_purchases_per_customer_limit, $productPrice, $options)->associate(Product::class);

                ShoppingCart::removeInvalidItems();

                return redirect()->back()->with('error', Translation::get('product-only-x-purchase-per-customer', 'cart', 'You can only purchase :quantity: of this product', 'text', [
                    'quantity' => $product->limit_purchases_per_customer_limit,
                ]))->withInput();
            }

            Cart::add($product->id, $product->name, $quantity, $productPrice, $options)->associate(Product::class);
        }

        $redirectChoice = Customsetting::get('add_to_cart_redirect_to', Sites::getActive(), 'same');
        if ($redirectChoice == 'same') {
            $redirectUrl = url()->previous();
        } elseif ($redirectChoice == 'cart') {
            $redirectUrl = ShoppingCart::getCartUrl();
        } elseif ($redirectChoice == 'checkout') {
            $redirectUrl = ShoppingCart::getCheckoutUrl();
        }

        ShoppingCart::removeInvalidItems();

        return redirect($redirectUrl)->with('success', Translation::get('product-added-to-cart', 'cart', 'The product has been added to your cart'));

        $this->checkCart($response['status'], $response['message']);
    }

    public function render()
    {
        return view('qcommerce-ecommerce-core::frontend.cart.add-to-cart');
    }
}
