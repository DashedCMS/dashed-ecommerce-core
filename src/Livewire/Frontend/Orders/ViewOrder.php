<?php

namespace Qubiqx\QcommerceEcommerceCore\Livewire\Frontend\Orders;

use Illuminate\Database\Eloquent\Collection;
use Livewire\Component;
use Qubiqx\QcommerceEcommerceCore\Models\Order;

class ViewOrder extends Component
{
    public Order $order;
    public Collection $orderProducts;

    public function mount(Order $order)
    {
        $this->order = $order;
        $this->orderProducts = $order->orderProducts;
        $this->notes = $order->publicLogs;
    }

    public function render()
    {
        return view('qcommerce-ecommerce-core::frontend.orders.view-order');
    }
}
