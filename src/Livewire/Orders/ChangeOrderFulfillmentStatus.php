<?php

namespace Dashed\DashedEcommerceCore\Livewire\Orders;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Filament\Notifications\Notification;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\OrderLog;

class ChangeOrderFulfillmentStatus extends Component
{
    public $order;
    public $fulfillmentStatus;

    public function mount(Order $order)
    {
        $this->order = $order;
        $this->fulfillmentStatus = $order->fulfillment_status;
    }

    public function render()
    {
        return view('dashed-ecommerce-core::orders.components.change-fulfillment-status');
    }

    public function submit()
    {
        if ($this->order->fulfillment_status == $this->fulfillmentStatus) {
            $this->emit('notify', [
                'status' => 'error',
                'message' => 'Bestelling heeft al deze fulfillment status',
            ]);

            return;
        }

        $this->order->changeFulfillmentStatus($this->fulfillmentStatus);

        $orderLog = new OrderLog();
        $orderLog->order_id = $this->order->id;
        $orderLog->user_id = Auth::user()->id;
        $orderLog->tag = 'order.changed-fulfillment-status-to-' . $this->fulfillmentStatus;
        $orderLog->save();

        $this->emit('refreshPage');
        Notification::make()
            ->success()
            ->title('Bestelling fulfillment status aangepast')
            ->send();
    }
}
