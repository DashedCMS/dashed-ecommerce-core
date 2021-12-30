<?php

namespace Qubiqx\QcommerceEcommerceCore\Livewire\Orders;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Qubiqx\QcommerceEcommerceCore\Models\OrderLog;

class ChangeOrderFulfillmentStatus extends Component
{
    public $order;
    public $fulfillmentStatus;

    public function mount($order)
    {
        $this->order = $order;
        $this->fulfillmentStatus = $order->fulfillment_status;
    }

    public function render()
    {
        return view('qcommerce-ecommerce-core::orders.components.change-fulfillment-status');
    }

    public function update()
    {
        $this->order->changeFulfillmentStatus($this->fulfillmentStatus);

        $orderLog = new OrderLog();
        $orderLog->order_id = $this->order->id;
        $orderLog->user_id = Auth::user()->id;
        $orderLog->tag = 'order.changed-fulfillment-status-to-' . $this->fulfillmentStatus;
        $orderLog->save();

        $this->emit('refreshPage');
        $this->emit('notify', [
            'status' => 'success',
            'message' => 'Bestelling fulfillment status aangepast',
        ]);
    }
}
