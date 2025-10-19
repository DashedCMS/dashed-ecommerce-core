<?php

namespace Dashed\DashedEcommerceCore\Livewire\Orders\Infolists;

use Livewire\Component;
use Illuminate\Support\Str;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Contracts\HasSchemas;
use Dashed\DashedEcommerceCore\Models\Order;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Concerns\InteractsWithSchemas;

class ViewStatusses extends Component implements HasSchemas
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
     * Blade verwacht waarschijnlijk $this->infolist,
     * dus we noemen de methode exact zo.
     */
    public function infolist(Schema $schema): Schema
    {
        // Verzamel labels
        $labels = $this->order->statusLabels;
        $labels[] = $this->order->orderStatus();
        $labels[] = $this->order->fulfillmentStatus();

        foreach ($this->order->fulfillmentCompanies() as $key => $fulfillmentCompany) {
            $labels[] = [
                'color' => $this->order->orderProducts()
                    ->where('fulfillment_provider', $key)
                    ->where('send_to_fulfiller', false)
                    ->count() ? 'warning' : 'success',
                'status' => 'Fulfillment voor ' . $fulfillmentCompany,
            ];
        }

        // Bouw TextEntry's met unieke namen
        $statusEntries = [];
        $i = 0;

        foreach ($labels as $label) {
            $status = is_array($label) ? ($label['status'] ?? '') : (string) $label;
            $color = is_array($label) ? ($label['color'] ?? 'gray') : 'gray';

            $name = 'status_' . $i . '_' . Str::slug($status) ?: 'status_' . $i;

            $statusEntries[] = TextEntry::make($name)
                ->hiddenLabel()
                ->state($status)
                ->badge()
                ->color($color);

            $i++;
        }

        // Credit facturen (ook unieke namen)
        foreach ($this->order->creditOrders as $creditOrder) {
            $statusEntries[] = TextEntry::make('credit_invoice_' . $creditOrder->id)
                ->hiddenLabel()
                ->state('Credit ' . $creditOrder->invoice_id)
                ->url(route('filament.dashed.resources.orders.view', [$creditOrder]))
                ->badge()
                ->color('danger');
        }

        if ($this->order->credit_for_order_id) {
            $statusEntries[] = TextEntry::make('credit_for_invoice_' . $this->order->id)
                ->hiddenLabel()
                ->state('Credit voor ' . $this->order->parentCreditOrder->invoice_id)
                ->url(route('filament.dashed.resources.orders.view', [$this->order->parentCreditOrder]))
                ->badge()
                ->color('danger');
        }

        // Render als Schema (v4)
        return $schema
            ->record($this->order)
            ->components([
                Fieldset::make('Statussen')->columnSpanFull()
                    ->schema($statusEntries)
                    ->columns([
                        'default' => 2,
                        'sm' => count($statusEntries),
                        'md' => count($statusEntries),
                        'lg' => count($statusEntries),
                        'xl' => count($statusEntries),
                    ]),
            ]);
    }

    public function render()
    {
        return view('dashed-ecommerce-core::orders.components.infolists.plain-info-list');
    }
}
