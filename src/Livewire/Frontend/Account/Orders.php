<?php

namespace Qubiqx\QcommerceEcommerceCore\Livewire\Frontend\Account;

use Illuminate\Database\Eloquent\Collection;
use Livewire\Component;

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
