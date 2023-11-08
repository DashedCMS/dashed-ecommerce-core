<?php

namespace Dashed\DashedEcommerceCore\Livewire\Orders\Infolists;

use Livewire\Component;
use Filament\Infolists\Infolist;
use Filament\Forms\Contracts\HasForms;
use Filament\Infolists\Components\Fieldset;
use Dashed\DashedEcommerceCore\Models\Order;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Contracts\HasInfolists;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Infolists\Concerns\InteractsWithInfolists;

class ViewStatusses extends Component implements HasForms, HasInfolists
{
    use InteractsWithForms;
    use InteractsWithInfolists;

    public Order $order;

    protected $listeners = [
        'refreshData' => '$refresh',
    ];

    public function mount($order)
    {
        $this->order = $order;
    }

    public function infolist(Infolist $infolist): Infolist
    {
        $labels = $this->order->statusLabels;
        $labels = array_merge($labels, [$this->order->orderStatus()]);
        $labels = array_merge($labels, [$this->order->fulfillmentStatus()]);

        $statusses = [];

        foreach ($labels as $label) {
            $statusses[] = TextEntry::make($label['status'])
                ->hiddenLabel()
                ->getStateUsing($label['status'])
                ->badge()
                ->color($label['color']);
        }

        foreach ($this->order->creditOrders as $creditOrder) {
            $statusses[] = TextEntry::make('Credit factuur')
                ->hiddenLabel()
                ->getStateUsing('Credit ' . $creditOrder->invoice_id)
                ->url(route('filament.dashed.resources.orders.view', [$creditOrder]))
                ->badge()
                ->color('danger');
        }

        if ($this->order->credit_for_order_id) {
            $statusses[] = TextEntry::make('Credit factuur')
                ->hiddenLabel()
                ->getStateUsing('Credit voor ' . $this->order->parentCreditOrder->invoice_id)
                ->url(route('filament.dashed.resources.orders.view', [$this->order->parentCreditOrder]))
                ->badge()
                ->color('danger');
        }

        return $infolist
            ->record($this->order)
            ->schema([
                Fieldset::make('Statussen')
                    ->schema($statusses)
                    ->columns([
                        'default' => 2,
                        'sm' => count($statusses),
                        'md' => count($statusses),
                        'lg' => count($statusses),
                        'xl' => count($statusses),
                    ]),
            ]);
    }

    public function render()
    {
        return view('dashed-ecommerce-core::orders.components.infolists.plain-info-list');
    }
}
