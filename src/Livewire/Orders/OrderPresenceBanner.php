<?php

namespace Dashed\DashedEcommerceCore\Livewire\Orders;

use Livewire\Component;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Support\Orders\OrderPresence;

/**
 * Balk op de orderpagina: aan wie de bestelling is toegewezen en welke collega's
 * hem nu ook open hebben. Eigen component, zodat de poll van 20 seconden alleen
 * deze balk ververst en niet de hele orderpagina (met open modals) opnieuw rendert.
 */
class OrderPresenceBanner extends Component
{
    public Order $order;

    protected $listeners = [
        'order-assignment-changed' => '$refresh',
        'refreshData' => '$refresh',
    ];

    public function mount($order): void
    {
        $this->order = $order;
    }

    /** "Bekijkt nu": aangeroepen door wire:poll.20s in de view. */
    public function pingPresence(): void
    {
        if (auth()->id()) {
            OrderPresence::ping($this->order->id, (int) auth()->id());
        }
    }

    public function render()
    {
        $order = $this->order->fresh(['assignedUser']) ?? $this->order;

        return view('dashed-ecommerce-core::orders.components.order-presence-banner', [
            'assignedTo' => $order->assigned_to_user_id ? ($order->assignedUser?->name ?? '-') : null,
            'assignedAt' => $order->assigned_at,
            'otherViewers' => OrderPresence::otherViewerNames($order->id, auth()->id()),
        ]);
    }
}
