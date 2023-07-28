<?php

namespace Qubiqx\QcommerceEcommerceCore\Livewire\Frontend\Orders;

use Livewire\Component;
use Illuminate\Database\Eloquent\Collection;
use Qubiqx\QcommerceEcommerceCore\Models\Order;

class ViewOrder extends Component
{
    public Order $order;
    public Collection $orderProducts;

    public function mount(Order $order)
    {
        $this->order = $order;
        $this->orderProducts = $order->orderProducts;
        $this->notes = $order->notes;
    }

    public function render()
    {
        return view('qcommerce-ecommerce-core::frontend.orders.view-order');
    }
}
