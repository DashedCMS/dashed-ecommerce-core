<?php

namespace Qubiqx\QcommerceEcommerceCore\Livewire\Frontend\Cart;

use Livewire\Component;

class CartCount extends Component
{

    public $cartCount = 0;

    protected $listeners = [
        'refreshCart' => 'mount'
    ];

    public function mount()
    {
        $this->cartCount = \Cart::getTotalQuantity();
    }

    public function render()
    {
        return view('qcommerce.livewire.cart.cart-count');
    }
}
