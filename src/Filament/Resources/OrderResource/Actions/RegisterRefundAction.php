<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources\OrderResource\Actions;

use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\OrderLog;
use Dashed\DashedEcommerceCore\Classes\CurrencyHelper;

class RegisterRefundAction
{
    public static function make(Order $order): Action
    {
        return Action::make('registerRefund')
            ->label(__('Terugstorting registreren'))
            ->icon('heroicon-o-arrow-uturn-left')
            ->color('warning')
            ->button()
            ->visible(fn () => $order->overpaidAmount() > 0)
            ->modalDescription(fn () => __('Er is :bedrag te veel betaald. Registreer hier wat je hebt teruggestort.', ['bedrag' => CurrencyHelper::formatPrice($order->overpaidAmount())]))
            ->form([
                // De grenzen staan hier zodat een typefout (200 waar 20 te veel
                // betaald is) een nette validatiemelding geeft in plaats van de
                // onbehandelde InvalidArgumentException uit handle(). Die
                // exception blijft staan als laatste verdediging voor aanroepen
                // die niet via dit formulier lopen.
                TextInput::make('amount')
                    ->label(__('Bedrag'))
                    ->numeric()
                    ->required()
                    ->minValue(0.01)
                    ->maxValue(round($order->overpaidAmount(), 2))
                    ->default($order->overpaidAmount()),
                Textarea::make('note')
                    ->label(__('Opmerking'))
                    ->nullable(),
            ])
            ->action(function (array $data) use ($order) {
                (new self())->handle($order, $data);

                Notification::make()
                    ->title(__('Terugstorting geregistreerd'))
                    ->success()
                    ->send();
            });
    }

    public function handle(Order $order, array $data): void
    {
        $amount = (float) $data['amount'];

        if ($amount <= 0 || $amount > $order->overpaidAmount() + 0.001) {
            throw new \InvalidArgumentException('Invalid amount.');
        }

        $order->orderPayments()->create([
            'status' => 'paid',
            'amount' => 0 - round($amount, 2),
            'psp' => 'own',
            'payment_method' => 'refund',
            'attributes' => [
                'note' => $data['note'] ?? null,
                'manual' => true,
            ],
        ]);

        OrderLog::createLog(
            orderId: $order->id,
            tag: 'order.refund.registered',
            note: 'Teruggestort: ' . CurrencyHelper::formatPrice($amount),
        );
    }
}
