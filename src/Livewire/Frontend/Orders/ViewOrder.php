<?php

namespace Dashed\DashedEcommerceCore\Livewire\Frontend\Orders;

use Livewire\Component;
use Dashed\DashedEcommerceCore\Models\Order;
use Illuminate\Database\Eloquent\Collection;

class ViewOrder extends Component
{
    public Order $order;
    public Collection $orderProducts;
    public Collection $notes;

    public function mount(Order $order)
    {
        $this->order = $order;
        $this->orderProducts = $order->orderProducts;
        $this->notes = $order->publicLogs;
    }

    public function render()
    {
        return view(env('SITE_THEME', 'dashed') . '.orders.view-order');
    }
}
