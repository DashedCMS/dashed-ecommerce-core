<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources\OrderResource\Actions;

use Dashed\DashedEcommerceCore\Mail\PaymentLinkMail;
use Dashed\DashedEcommerceCore\Models\Order;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class SendPaymentLinkAction
{
    public static function make(Order $order): Action
    {
        return Action::make('sendPaymentLink')
            ->label(__('Stuur betaallink'))
            ->icon('heroicon-o-link')
            ->button()
            ->visible(fn () => $order->outstandingAmount() > 0)
            ->form([
                TextInput::make('amount')
                    ->label(__('Bedrag'))
                    ->numeric()
                    ->required()
                    ->default($order->outstandingAmount()),
                TextInput::make('email')
                    ->label(__('E-mailadres'))
                    ->email()
                    ->required()
                    ->default($order->email),
            ])
            ->action(function (array $data) use ($order) {
                (new self())->handle($order, $data);

                Notification::make()
                    ->title(__('Betaallink verstuurd'))
                    ->success()
                    ->send();
            });
    }

    public function handle(Order $order, array $data): void
    {
        $paymentUrl = url('/pay/order/' . $order->hash . '/remainder');

        if ($order->isConcept()) {
            $order->status = 'pending';
            $order->save();
            $order->createInvoice();
        }

        if (! empty($data['email'])) {
            Mail::to($data['email'])->send(new PaymentLinkMail($order, (float) $data['amount'], $paymentUrl));
        }
    }
}
