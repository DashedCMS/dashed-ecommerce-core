<?php

namespace Dashed\DashedEcommerceCore\Livewire\Orders;

use Livewire\Component;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Actions\Contracts\HasActions;
use Filament\Schemas\Contracts\HasSchemas;
use Dashed\DashedEcommerceCore\Models\Order;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Schemas\Concerns\InteractsWithSchemas;

class AddPaymentToOrder extends Component implements HasSchemas, HasActions
{
    use InteractsWithSchemas;
    use InteractsWithActions;

    public Order $order;

    public function mount(Order $order)
    {
        $this->order = $order;
    }

    public function action(): Action
    {

        return Action::make('action')
            ->label("Voeg betaling toe (al voldaan: {$this->order->paidAmount})")
            ->color('primary')
            ->fillForm(function () {
                $paymentAmount = $this->order->total - $this->order->orderPayments->where('status', 'paid')->sum('amount');
                if ($paymentAmount < 0) {
                    $paymentAmount = 0;
                }

                return [
                    'paymentAmount' => $paymentAmount,
                ];
            })
            ->schema([
                TextInput::make('paymentAmount')
                    ->label('Het bedrag dat betaald is')
                    ->helperText(fn () => "Het bedrag dat is betaald (al voldaan: {$this->order->paidAmount})")
                    ->required()
                    ->numeric()
                    ->minValue(0.01),
            ])
            ->action(function ($data) {
                if ($this->order->status != 'paid') {
                    $orderPayment = $this->order->orderPayments()->create([
                        'psp' => 'own',
                        'payment_method' => 'manual_payment',
                        'amount' => $data['paymentAmount'],
                    ]);

                    $newPaymentStatus = $orderPayment->changeStatus('paid');
                    $this->order->changeStatus($newPaymentStatus);
                }

                Notification::make()
                    ->title('Bestelling gemarkeerd als betaald')
                    ->success()
                    ->send();

                $this->dispatch('refreshData');
            });
    }

    public function render()
    {
        return view('dashed-ecommerce-core::orders.components.plain-action');
    }
}
