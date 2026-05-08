<?php

namespace Dashed\DashedEcommerceCore\Events\Orders;

use Illuminate\Queue\SerializesModels;
use Dashed\DashedEcommerceCore\Models\Order;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;

/**
 * Wordt gedispatched zodra Order::changeFulfillmentStatus() de
 * fulfillment_status daadwerkelijk wijzigt. Listeners filteren zelf op de
 * gewenste $newStatus (handled / shipped / packed / ready_for_pickup / ...).
 */
class OrderFulfillmentStatusChangedEvent
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public Order $order,
        public ?string $oldStatus,
        public string $newStatus,
    ) {
    }
}
