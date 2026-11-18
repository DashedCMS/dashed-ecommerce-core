<?php

namespace Dashed\DashedEcommerceCore\Livewire\Orders;

use Livewire\Component;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Classes\OrderAddOns\Display;
use Dashed\DashedEcommerceCore\Filament\Resources\OrderResource;

/**
 * Amber banner at the top of the order view. On a parent: which add-on orders ship
 * in the same box, with their lines. On an add-on: which order it ships with, or
 * that it ships separately (with the reason).
 */
class OrderAddOnBanner extends Component
{
    public Order $order;

    protected $listeners = [
        'refreshData' => '$refresh',
    ];

    public function mount($order): void
    {
        $this->order = $order;
    }

    public function render()
    {
        if (! Display::installed()) {
            return '<div></div>';
        }

        $order = $this->order->fresh() ?? $this->order;
        $link = $order->addOnLink;
        $addOns = $link?->isLinked() ? collect() : Display::linkedAddOns($order);

        return view('dashed-ecommerce-core::orders.components.order-add-on-banner', [
            'link' => $link,
            'parent' => $link?->parentOrder,
            'addOns' => $addOns->map(fn (Order $addOn) => [
                'order' => $addOn,
                'number' => Display::number($addOn),
                'url' => OrderResource::getUrl('view', ['record' => $addOn]),
                'lines' => Display::boxLines($addOn),
            ]),
            'itemsCount' => $addOns->sum(fn (Order $addOn) => Display::itemsCount($addOn)),
        ]);
    }
}
