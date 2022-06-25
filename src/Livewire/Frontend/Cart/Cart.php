<?php

namespace Qubiqx\QcommerceEcommerceCore\Livewire\Frontend\Cart;

use Livewire\Component;
use Illuminate\Support\Collection;
use Qubiqx\QcommerceEcommerceCore\Models\Product;
use Qubiqx\QcommerceTranslations\Models\Translation;
use Qubiqx\QcommerceEcommerceCore\Models\DiscountCode;
use Qubiqx\QcommerceEcommerceCore\Classes\ShoppingCart;
use Qubiqx\QcommerceEcommerceCore\Livewire\Concerns\CartActions;

class Cart extends Component
{
    use CartActions;

//    public ?Collection $cartItems = null;
    public string $discountCode = '';
    public $discount;
    public $subtotal;
    public $tax;
    public $total;

    public function mount(Product $product)
    {
        $this->discountCode = session('discountCode');
        $this->checkCart();
        $this->fillPrices();
    }

    public function fillPrices()
    {
        $this->subtotal = ShoppingCart::subtotal(true);
        $this->discount = ShoppingCart::totalDiscount(true);
        $this->tax = ShoppingCart::btw(true);
        $this->total = ShoppingCart::total(true);
    }

    public function getCartItemsProperty()
    {
        return ShoppingCart::cartItems();
    }

    public function updated()
    {
        $this->fillPrices();
    }

    public function rules()
    {
        return [
//            'extras.*.value' => ['nullable'],
        ];
    }

    public function changeQuantity(string $rowId, int $quantity)
    {
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
        if (! $this->discountCode) {
            session(['discountCode' => '']);
            $this->discountCode = '';
            $this->fillPrices();

            return $this->checkCart('error', Translation::get('discount-code-not-valid', 'cart', 'The discount code is not valid'));
        }

        $discountCode = DiscountCode::usable()->where('code', $this->discountCode)->first();

        if (! $discountCode || ! $discountCode->isValidForCart()) {
            session(['discountCode' => '']);
            $this->discountCode = '';
            $this->fillPrices();

            return $this->checkCart('error', Translation::get('discount-code-not-valid', 'cart', 'The discount code is not valid'));
        }

        session(['discountCode' => $discountCode->code]);
        $this->fillPrices();

        return $this->checkCart('success', Translation::get('discount-code-applied', 'cart', 'The discount code has been applied and discount has been calculated'));
    }

    public function render()
    {
        return view('qcommerce-ecommerce-core::frontend.cart.cart');
    }
}
