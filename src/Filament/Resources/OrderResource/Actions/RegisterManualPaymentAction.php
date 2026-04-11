<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources\OrderResource\Actions;

use Illuminate\Support\Str;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Dashed\DashedEcommerceCore\Models\Order;

class RegisterManualPaymentAction
{
    public static function make(Order $order): Action
    {
        return Action::make('registerManualPayment')
            ->label(__('Registreer handmatige betaling'))
            ->icon('heroicon-o-banknotes')
            ->button()
            ->visible(fn () => $order->outstandingAmount() > 0)
            ->form([
                TextInput::make('amount')
                    ->label(__('Bedrag'))
                    ->numeric()
                    ->required()
                    ->default($order->outstandingAmount()),
                Select::make('payment_method')
                    ->label(__('Betaalmethode'))
                    ->options([
                        'cash' => __('Contant'),
                        'pin' => __('Pin'),
                        'bank_transfer' => __('Overboeking'),
                    ])
                    ->default('cash')
                    ->required(),
                Textarea::make('note')
                    ->label(__('Opmerking'))
                    ->nullable(),
            ])
            ->action(function (array $data) use ($order) {
                (new self())->handle($order, $data);

                Notification::make()
                    ->title(__('Betaling geregistreerd'))
                    ->success()
                    ->send();
            });
    }

    public function handle(Order $order, array $data): void
    {
        $amount = (float) $data['amount'];

        if ($amount <= 0 || $amount > $order->outstandingAmount() + 0.001) {
            throw new \InvalidArgumentException('Invalid amount.');
        }

        $order->orderPayments()->create([
            'status' => 'paid',
            'amount' => $amount,
            'psp' => 'own',
            'payment_method' => $data['payment_method'],
            'hash' => (string) Str::uuid(),
            'attributes' => [
                'note' => $data['note'] ?? null,
                'manual' => true,
            ],
        ]);

        if ($order->isConcept()) {
            $order->status = 'pending';
            $order->save();
        }

        if (abs($order->outstandingAmount()) < 0.001) {
            $order->changeStatus('paid');
        } else {
            $order->changeStatus('partially_paid');
        }

        if (! $order->fresh()->invoice_id || in_array($order->fresh()->invoice_id, ['PROFORMA', 'RETURN'], true)) {
            $order->createInvoice();
        }
    }
}
