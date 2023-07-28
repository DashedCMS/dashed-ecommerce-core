<?php

namespace Qubiqx\QcommerceEcommerceCore\Livewire\Frontend\Orders;

use Livewire\Component;
use Qubiqx\QcommerceEcommerceCore\Models\Order;

class ViewOrder extends Component
{
    public Order $order;

    public function mount(Order $order)
    {
        $this->order = $order;
    }

    public function render()
    {
        return view('qcommerce-ecommerce-core::frontend.orders.view-order');
    }
}
