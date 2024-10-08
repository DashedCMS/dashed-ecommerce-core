<?php

namespace Dashed\DashedEcommerceCore\Livewire\Frontend\Cart;

use Livewire\Component;
use Gloudemans\Shoppingcart\Facades\Cart;
use Dashed\DashedEcommerceCore\Classes\ShoppingCart;

class CartCount extends Component
{
    public $cartCount = 0;
    public string $cartType = 'default';

    protected $listeners = [
        'refreshCart',
    ];

    public function mount($cartType = 'default')
    {
        $this->cartType = $cartType;
        $this->refreshCart();
    }

    public function refreshCart()
    {
        ShoppingCart::setInstance($this->cartType);
        $this->cartCount = Cart::count();
    }

    public function render()
    {
        return view(env('SITE_THEME', 'dashed') . '.cart.cart-count');
    }
}
