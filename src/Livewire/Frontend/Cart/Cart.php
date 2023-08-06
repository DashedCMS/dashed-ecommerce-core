<?php

namespace Dashed\DashedEcommerceCore\Livewire\Frontend\Cart;

use Livewire\Component;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Classes\ShoppingCart;
use Dashed\DashedEcommerceCore\Livewire\Concerns\CartActions;

class Cart extends Component
{
    use CartActions;

    public string $discountCode = '';
    public $discount;
    public $subtotal;
    public $tax;
    public $total;
    public $paymentCosts;
    public $shippingCosts;
    public $depositAmount;
    public bool $postpayPaymentMethod = false;
    public \Illuminate\Database\Eloquent\Collection|array $shippingMethods = [];
    public array $paymentMethods = [];
    public array $depositPaymentMethods = [];

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

    public function render()
    {
        return view('dashed-ecommerce-core::frontend.cart.cart');
    }
}
