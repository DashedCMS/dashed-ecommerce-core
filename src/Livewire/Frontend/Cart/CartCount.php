<?php

namespace Dashed\DashedEcommerceCore\Livewire\Frontend\Cart;

use Livewire\Component;
use Gloudemans\Shoppingcart\Facades\Cart;

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
        cartHelper()->setCartType($this->cartType);
        $this->cartCount = Cart::count();
    }

    public function render()
    {
        return view(config('dashed-core.site_theme') . '.cart.cart-count');
    }
}
