<?php

namespace Dashed\DashedEcommerceCore\Livewire\Frontend\Cart;

use Livewire\Component;
use Gloudemans\Shoppingcart\Facades\Cart;

class CartCount extends Component
{
    public $cartCount = 0;

    protected $listeners = [
        'refreshCart' => 'mount',
    ];

    public function mount()
    {
        $this->cartCount = Cart::count();
    }

    public function render()
    {
        return view('dashed-ecommerce-core::frontend.cart.cart-count');
    }
}
