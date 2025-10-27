<?php

namespace Dashed\DashedEcommerceCore\Livewire\Orders\Infolists;

use Livewire\Component;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Contracts\HasSchemas;
use Dashed\DashedEcommerceCore\Models\Order;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Concerns\InteractsWithSchemas;

class PaymentsList extends Component implements HasSchemas
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

    public function infolist(Schema $schema): Schema
    {
        $paymentsSchema = [];

        foreach ($this->order->orderPayments as $orderPayment) {
            $pid = $orderPayment->id ?? spl_object_id($orderPayment);

            $paymentsSchema[] = Fieldset::make('payment_' . $pid)
                ->label('Betaling van ' . $orderPayment->created_at->format('d-m-Y H:i'))
                ->schema([
                    TextEntry::make('psp_' . $pid)
                        ->label('PSP')
                        ->state(fn () => $orderPayment->psp ?: '-'),

                    TextEntry::make('psp_id_' . $pid)
                        ->label('PSP ID')
                        ->state(fn () => $orderPayment->psp_id ?: '-'),

                    TextEntry::make('payment_method_' . $pid)
                        ->label('Betaalmethode')
                        ->state(fn () => $orderPayment->payment_method ?: ($orderPayment->paymentMethod->name ?? '-')),

                    TextEntry::make('amount_' . $pid)
                        ->label('Bedrag')
                        ->state(fn () => $orderPayment->amount)
                        ->money('EUR'),

                    TextEntry::make('status_' . $pid)
                        ->label('Status')
                        ->state(fn () => $orderPayment->status)
                        ->badge()
                        ->color(fn () => match ($orderPayment->status) {
                            'paid' => 'success',
                            'pending' => 'warning',
                            'failed', 'cancelled' => 'danger',
                            default => 'gray',
                        }),
                ])
                ->columns(3)
                ->columnSpanFull();
        }

        return $schema
            ->record($this->order)
            ->components([
                Fieldset::make('payments_root')
                    ->label('Betalingen')
                    ->schema($paymentsSchema)
                    ->columnSpanFull(),
            ]);
    }

    public function render()
    {
        return view('dashed-ecommerce-core::orders.components.infolists.plain-info-list');
    }
}
