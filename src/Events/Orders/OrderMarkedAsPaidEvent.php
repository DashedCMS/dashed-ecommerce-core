<?php

namespace Dashed\DashedEcommerceCore\Events\Orders;

use Throwable;
use Illuminate\Queue\SerializesModels;
use Dashed\DashedCore\Models\Customsetting;
use Illuminate\Broadcasting\PrivateChannel;
use Dashed\DashedEcommerceCore\Models\Order;
use Illuminate\Foundation\Events\Dispatchable;
use Dashed\DashedEcommerceCore\Models\OrderLog;
use Illuminate\Broadcasting\InteractsWithSockets;
use Dashed\DashedEcommerceCore\Models\ApiSubscriptionLog;

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
        if ($printReceiptFromOrder && $order->order_origin != 'pos') {
            $this->order->printReceipt();
        }

        $apis = Customsetting::get('apis', null, []) ?? [];
        foreach ($apis as $api) {
            if (! is_array($api) || empty($api['class']) || ! class_exists($api['class'])) {
                continue;
            }

            $shouldDispatch = $order->marketing || ! empty($api['sync_always']);
            if (! $shouldDispatch) {
                continue;
            }

            $email = mb_strtolower(trim((string) ($order->email ?? '')));

            try {
                $api['class']::dispatch($order, $api);

                if ($email !== '') {
                    ApiSubscriptionLog::record(
                        email: $email,
                        apiClass: $api['class'],
                        source: ApiSubscriptionLog::SOURCE_ORDER,
                        status: ApiSubscriptionLog::STATUS_SUCCESS,
                    );
                }
            } catch (Throwable $e) {
                report($e);
                if ($email !== '') {
                    ApiSubscriptionLog::record(
                        email: $email,
                        apiClass: $api['class'],
                        source: ApiSubscriptionLog::SOURCE_ORDER,
                        status: ApiSubscriptionLog::STATUS_FAILED,
                        error: mb_substr($e->getMessage(), 0, 1000),
                    );
                }
            }
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
