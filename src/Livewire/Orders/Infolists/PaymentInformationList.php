<?php

namespace Dashed\DashedEcommerceCore\Livewire\Orders\Infolists;

use Livewire\Component;
use Illuminate\Support\Carbon;
use Filament\Schemas\Contracts\HasSchemas;
use Dashed\DashedEcommerceCore\Models\Order;
use Filament\Schemas\Concerns\InteractsWithSchemas;

class PaymentInformationList extends Component implements HasSchemas
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
     * @return array<string, string>
     */
    public function details(): array
    {
        $order = $this->order;

        $details = [
            __('Factuur ID') => $order->invoice_id,
            __('Bestellingsherkomst') => $order->order_origin,
            __('Betaalmethode') => $order->mainPaymentMethod?->name,
            __('PSP') => $order->psp,
            __('PSP ID') => $order->psp ? $order->psp_id : null,
            __('Verzendmethode') => $order->shippingMethod->name ?? null,
            __('Marketing geaccepteerd') => $order->marketing ? __('Ja') : __('Nee'),
            __('BTW verlegd') => $order->vat_reverse_charge ? __('Ja') : __('Nee'),
            __('Te betalen voor') => $order->payment_due_at ? Carbon::parse($order->payment_due_at)->format('d-m-Y') : null,
            __('Verstuurde herinneringen') => $order->paymentReminders->map(
                fn ($reminder) => __('Stap :stage op :date', ['stage' => $reminder->stage, 'date' => $reminder->sent_at->format('d-m-Y')])
            )->join(', '),
            __('IP') => $order->ip,
        ];

        foreach ($order->customOrderFields() as $label => $value) {
            $details[$label] = $value;
        }

        return array_filter($details, fn ($value): bool => filled($value));
    }

    public function render()
    {
        return view('dashed-ecommerce-core::orders.components.order-details');
    }
}
