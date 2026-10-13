<?php

namespace Dashed\DashedEcommerceCore\Livewire\Orders\Infolists;

use Livewire\Component;
use Filament\Schemas\Contracts\HasSchemas;
use Dashed\DashedEcommerceCore\Models\Order;
use Filament\Schemas\Concerns\InteractsWithSchemas;

class LogsList extends Component implements HasSchemas
{
    use InteractsWithSchemas;

    public Order $order;

    protected $listeners = [
        'refreshData' => '$refresh',
    ];

    public function mount($order)
    {
        $this->order = $order;
    }

    /**
     * De soort van een logregel, voor het filter boven de tijdlijn.
     */
    public static function typeFor(string $tag): string
    {
        if (str_contains($tag, 'mail') || $tag === 'order.t&t.send') {
            return 'mail';
        }

        return str_contains($tag, 'note') ? 'note' : 'status';
    }

    public function render()
    {
        return view('dashed-ecommerce-core::orders.components.order-timeline', [
            'logs' => $this->order->logs()->where('is_debug_log', 0)->with('user')->orderByDesc('created_at')->orderByDesc('id')->get(),
        ]);
    }
}
