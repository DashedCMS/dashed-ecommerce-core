<?php

namespace Dashed\DashedEcommerceCore\Events\Orders;

use Illuminate\Queue\SerializesModels;
use Dashed\DashedCore\Models\Customsetting;
use Illuminate\Broadcasting\PrivateChannel;
use Dashed\DashedEcommerceCore\Models\Order;
use Illuminate\Foundation\Events\Dispatchable;
use Dashed\DashedEcommerceCore\Models\OrderLog;
use Illuminate\Broadcasting\InteractsWithSockets;

class OrderMarkedAsPaidEvent
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public $order;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct(Order $order)
    {
        $this->order = $order;

        $orderLog = new OrderLog();
        $orderLog->order_id = $order->id;
        $orderLog->user_id = null;
        $orderLog->tag = 'order.marked_as_paid_event.dispatched';
        $orderLog->save();

        $printReceiptFromOrder = Customsetting::get('pos_auto_print_other_orders', null, false);
        if ($printReceiptFromOrder) {
            $this->order->printReceipt();
        }
    }

    //    /**
    //     * Get the channels the event should broadcast on.
    //     *
    //     * @return \Illuminate\Broadcasting\Channel|array
    //     */
    //    public function broadcastOn()
    //    {
    //        return new PrivateChannel('channel-name');
    //    }
}
