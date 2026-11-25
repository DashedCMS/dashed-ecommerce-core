<?php

namespace Dashed\DashedEcommerceCore\Livewire\Orders;

use Livewire\Component;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\CarrierApiLog;
use Dashed\DashedEcommerceCore\Filament\Resources\CarrierApiLogResource;

/**
 * Carrier-logboek card on the order page: the carrier requests for this
 * order, newest first. Only rendered for whoever may open the logbook.
 */
class CarrierApiLogsList extends Component
{
    public const LIMIT = 25;

    public Order $order;

    public function mount($order): void
    {
        $this->order = $order;
    }

    public function render()
    {
        $allowed = CarrierApiLogResource::isAllowed();
        $query = CarrierApiLog::query()->where('order_id', $this->order->id);

        return view('dashed-ecommerce-core::orders.components.carrier-api-logs', [
            'allowed' => $allowed,
            'logs' => $allowed ? (clone $query)->latest('created_at')->latest('id')->limit(self::LIMIT)->get() : collect(),
            'total' => $allowed ? $query->count() : 0,
            'indexUrl' => $allowed ? CarrierApiLogResource::getUrl('index') . '?order=' . $this->order->id : null,
        ]);
    }
}
