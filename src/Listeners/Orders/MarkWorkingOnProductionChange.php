<?php

namespace Dashed\DashedEcommerceCore\Listeners\Orders;

use Dashed\DashedEcommerceCore\Support\Orders\OrderPresence;

/**
 * Printstatus van een orderregel gewijzigd (OrderProductProductionReady /
 * -NotReady): wie dat deed "is ermee bezig". Leest het event defensief,
 * zodat het ook werkt als de payload net anders heet.
 */
class MarkWorkingOnProductionChange
{
    public function handle(object $event): void
    {
        $orderProduct = $event->orderProduct ?? null;
        $orderId = (int) ($event->orderId ?? $orderProduct?->order_id ?? 0);

        $userId = $event->userId ?? null;
        if ($userId === null && isset($event->user) && is_object($event->user)) {
            $userId = $event->user->id ?? null;
        }
        $userId = (int) ($userId ?? auth()->id() ?? 0);

        if ($orderId > 0 && $userId > 0) {
            OrderPresence::markWorking($orderId, $userId);
        }
    }
}
