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

class PaymentsList extends Component implements HasForms, HasInfolists
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
        $paymentsSchema = [];

        foreach ($this->order->orderPayments as $orderPayment) {
            $paymentsSchema[] =
                Fieldset::make('Betaling van ' . $orderPayment->created_at->format('d-m-Y H:i'))
                    ->schema([
                        TextEntry::make('psp')
                            ->label('PSP')
                            ->getStateUsing($orderPayment->psp),
                        TextEntry::make('psp_id')
                            ->getStateUsing($orderPayment->psp_id ?: '-')
                            ->label('PSP ID'),
                        TextEntry::make('payment_method')
                            ->getStateUsing($orderPayment->payment_method)
                            ->label('Betaalmethode'),
                        TextEntry::make('amount')
                            ->label('Bedrag')
                            ->getStateUsing($orderPayment->amount)
                            ->money('EUR'),
                        TextEntry::make('status')
                            ->getStateUsing($orderPayment->status)
                            ->label('Status'),
                    ])
                    ->columns(3)
                    ->columnSpanFull();
        }

        return $infolist
            ->record($this->order)
            ->schema([
                Fieldset::make('Betalingen')
                    ->schema($paymentsSchema),
            ]);
    }

    public function render()
    {
        return view('dashed-ecommerce-core::orders.components.infolists.plain-info-list');
    }
}
