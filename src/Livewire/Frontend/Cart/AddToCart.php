<?php

namespace Qubiqx\QcommerceEcommerceCore\Livewire\Frontend\Cart;

use Illuminate\Database\Eloquent\Collection;
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
    public array $filters = [];
    public ?Collection $extras = null;
    public string|int $quantity = 1;

    public function mount(Product $product)
    {
        $this->product = $product;
        $this->filters = $this->product->filters();
        $this->extras = $this->product->allProductExtras();

    }

    public function rules()
    {
        return [
            'extras.*.value' => ['nullable']
        ];
    }

    public function setQuantity(int $quantity)
    {
        $this->quantity = $quantity;
    }

    public function updatedQuantity()
    {
        if (!$this->quantity) {
            $this->quantity = 1;
        } elseif ($this->quantity < 1) {
            $this->quantity = 1;
        } elseif ($this->quantity > $this->product->stock()) {
            $this->quantity = $product->stock();
        }
    }

    public function addToCart()
    {
        $cartUpdated = false;
        $productPrice = $this->product->currentPrice;
        $options = [];
        foreach ($this->extras as $productExtra) {
            if ($productExtra->type == 'single') {
                $productValue = $productExtra['value'] ?? null;
                if ($productExtra->required && !$productValue) {
                    return $this->checkCart('error', Translation::get('not-all-required-options-chosen', 'cart', 'Not all extra`s have a selected option.'));
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
            } else {
                foreach ($productExtra->productExtraOptions as $option) {
                    //Todo: fix this and test with real webshop, for example with Russle
                    $productOptionValue = $option['value'] ?? null;
//                    $productOptionValue = $request['product-extra-' . $productExtra->id . '-' . $option->id];
                    if ($productExtra->required && !$productOptionValue) {
                        return $this->checkCart('error', Translation::get('not-all-required-options-chosen', 'cart', 'Not all extra`s have a selected option.'));
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

        $cartItems = ShoppingCart::cartItems();
        foreach ($cartItems as $cartItem) {
            //Todo: the comparison for options does not work
            if ($cartItem->model->id == $this->product->id && $options == $cartItem->options) {
                $newQuantity = $cartItem->qty + $this->quantity;

                if ($this->product->limit_purchases_per_customer && $newQuantity > $this->product->limit_purchases_per_customer_limit) {
                    Cart::update($cartItem->rowId, $this->product->limit_purchases_per_customer_limit);

                    return $this->checkCart('error', Translation::get('product-only-x-purchase-per-customer', 'cart', 'You can only purchase :quantity: of this product', 'text', [
                        'quantity' => $this->product->limit_purchases_per_customer_limit,
                    ]));
                }

                Cart::update($cartItem->rowId, $newQuantity);
                $cartUpdated = true;
            }
        }

        if (!$cartUpdated) {
            if ($this->product->limit_purchases_per_customer && $this->quantity > $this->product->limit_purchases_per_customer_limit) {
                Cart::add($this->product->id, $this->product->name, $this->product->limit_purchases_per_customer_limit, $productPrice, $options)->associate(Product::class);

                return $this->checkCart('error', Translation::get('product-only-x-purchase-per-customer', 'cart', 'You can only purchase :quantity: of this product', 'text', [
                    'quantity' => $this->product->limit_purchases_per_customer_limit,
                ]));
            }

            Cart::add($this->product->id, $this->product->name, $this->quantity, $productPrice, $options)->associate(Product::class);
        }

        $redirectChoice = Customsetting::get('add_to_cart_redirect_to', Sites::getActive(), 'same');
        if ($redirectChoice == 'same') {
            return $this->checkCart('success', Translation::get('product-added-to-cart', 'cart', 'The product has been added to your cart'));
        } elseif ($redirectChoice == 'cart') {
            $this->checkCart();
            return redirect(ShoppingCart::getCartUrl())->with('success', Translation::get('product-added-to-cart', 'cart', 'The product has been added to your cart'));
        } elseif ($redirectChoice == 'checkout') {
            $this->checkCart();
            return redirect(ShoppingCart::getCheckoutUrl())->with('success', Translation::get('product-added-to-cart', 'cart', 'The product has been added to your cart'));
        }
    }

    public function render()
    {
        return view('qcommerce-ecommerce-core::frontend.cart.add-to-cart');
    }
}
