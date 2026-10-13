<?php

namespace Dashed\DashedEcommerceCore\Livewire\Orders\Infolists;

use Livewire\Component;
use Filament\Schemas\Contracts\HasSchemas;
use Dashed\DashedEcommerceCore\Models\Order;
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
     * @return array<int, array{status: string, color: string, url: ?string}>
     */
    public function badges(): array
    {
        $badges = [];

        foreach ([...$this->order->statusLabels, $this->order->orderStatus(), $this->order->fulfillmentStatus()] as $label) {
            $badges[] = [
                'status' => is_array($label) ? ($label['status'] ?? '') : (string) $label,
                'color' => is_array($label) ? ($label['color'] ?? 'gray') : 'gray',
                'url' => null,
            ];
        }

        foreach ($this->order->fulfillmentCompanies() as $key => $fulfillmentCompany) {
            $badges[] = [
                'status' => 'Fulfillment voor ' . $fulfillmentCompany,
                'color' => $this->order->orderProducts()
                    ->where('fulfillment_provider', $key)
                    ->where('send_to_fulfiller', false)
                    ->count() ? 'warning' : 'success',
                'url' => null,
            ];
        }

        foreach ($this->order->creditOrders as $creditOrder) {
            $badges[] = [
                'status' => 'Credit ' . $creditOrder->invoice_id,
                'color' => 'danger',
                'url' => route('filament.dashed.resources.orders.view', [$creditOrder]),
            ];
        }

        if ($this->order->credit_for_order_id && $this->order->parentCreditOrder) {
            $badges[] = [
                'status' => 'Credit voor ' . $this->order->parentCreditOrder->invoice_id,
                'color' => 'danger',
                'url' => route('filament.dashed.resources.orders.view', [$this->order->parentCreditOrder]),
            ];
        }

        if ($this->order->credit_for_order_id && ($origin = $this->order->originReturn)) {
            $badges[] = [
                'status' => __('Ontstaan uit retour #:id', ['id' => $origin->id]),
                'color' => 'warning',
                'url' => route('filament.dashed.resources.order-returns.view', [$origin]),
            ];
        }

        return array_values(array_filter($badges, fn (array $badge): bool => filled($badge['status'])));
    }

    public function render()
    {
        return view('dashed-ecommerce-core::orders.components.status-badges');
    }
}
