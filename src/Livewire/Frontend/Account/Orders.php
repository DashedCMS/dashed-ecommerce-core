<?php

namespace Qubiqx\QcommerceEcommerceCore\Livewire\Frontend\Account;

use Livewire\Component;
use Illuminate\Database\Eloquent\Collection;

class Orders extends Component
{
    public Collection $orders;

    public function mount()
    {
        $this->orders = auth()->user()->orders()->with('products')->get();
    }

    public function render()
    {
        return view('qcommerce-ecommerce-core::frontend.account.orders');
    }
}
