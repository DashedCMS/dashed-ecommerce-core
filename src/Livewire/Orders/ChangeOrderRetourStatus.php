<?php

namespace Dashed\DashedEcommerceCore\Livewire\Orders;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Dashed\DashedEcommerceCore\Models\OrderLog;

class ChangeOrderRetourStatus extends Component
{
    public $order;
    public $retourStatus;

    public function mount($order)
    {
        $this->order = $order;
        $this->retourStatus = $order->retour_status;
    }

    public function render()
    {
        return view('dashed-ecommerce-core::orders.components.change-retour-status');
    }

    public function update()
    {
        $this->order->retour_status = $this->retourStatus;
        $this->order->save();

        $orderLog = new OrderLog();
        $orderLog->order_id = $this->order->id;
        $orderLog->user_id = Auth::user()->id;
        $orderLog->tag = 'order.changed-retour-status-to-' . $this->retourStatus;
        $orderLog->save();

        $this->emit('refreshPage');
        $this->emit('notify', [
            'status' => 'success',
            'message' => 'Bestelling retour status aangepast',
        ]);
    }
}
