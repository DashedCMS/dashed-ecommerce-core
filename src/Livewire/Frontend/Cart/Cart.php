<?php

namespace Dashed\DashedEcommerceCore\Livewire\Frontend\Cart;

use Dashed\DashedEcommerceCore\Classes\TikTokHelper;
use Dashed\DashedEcommerceCore\Livewire\Concerns\ProductCartActions;
use Illuminate\Support\Collection;
use Livewire\Component;
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
    public string $cartType = 'default';

    public function mount(string $cartType = 'default')
    {
        $this->cartType = $cartType;
        ShoppingCart::setInstance($this->cartType);
        $this->discountCode = session('discountCode', '');
        $this->checkCart();
        $this->fillPrices();

        $itemLoop = 0;
        $items = [];

        foreach ($this->cartItems as $cartItem) {
            $items[] = [
                'item_id' => $cartItem->model->id,
                'item_name' => $cartItem->model->name,
                'index' => $itemLoop,
                'discount' => $cartItem->model->discount_price > 0 ? number_format(($cartItem->model->discount_price - $cartItem->model->current_price), 2, '.', '') : 0,
                'item_category' => $cartItem->model->productCategories->first()?->name ?? null,
                'price' => number_format($cartItem->price, 2, '.', ''),
                'quantity' => $cartItem->qty,
            ];
            $itemLoop++;
        }

        $cartTotal =ShoppingCart::total(false);

        $this->dispatch('cartInitiated', [
            'cartTotal' => number_format($cartTotal, 2, '.', ''),
            'items' => $items,
            'tiktokItems' => TikTokHelper::getShoppingCartItems($cartTotal),
        ]);
    }

    public function fillPrices()
    {
        $shoppingCartAmounts = ShoppingCart::amounts(true);
        $this->subtotal = $shoppingCartAmounts['subTotal'];
        $this->discount = $shoppingCartAmounts['discount'];
        $this->tax = $shoppingCartAmounts['tax'];
        $this->total = $shoppingCartAmounts['total'];
        //        $this->subtotal = ShoppingCart::subtotal(true);
        //        $this->discount = ShoppingCart::totalDiscount(true);
        //        $this->tax = ShoppingCart::btw(true);
        //        $this->total = ShoppingCart::total(true);
        $this->getSuggestedProducts();
    }

    public function getCartItemsProperty()
    {
        return ShoppingCart::cartItems($this->cartType);
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
        return view(env('SITE_THEME', 'dashed') . '.cart.' . ($this->cartType != 'default' ? $this->cartType . '-' : '') . 'cart');
    }
}
