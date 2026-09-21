<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources\OrderResource\Actions;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Services\OnAccount\PaymentReminderSender;

class SendPaymentReminderAction
{
    public static function make(Order $order): Action
    {
        return Action::make('sendPaymentReminder')
            ->label(__('Herinnering sturen'))
            ->icon('heroicon-o-bell-alert')
            ->visible(fn () => $order->payment_due_at && $order->outstandingAmount() > 0)
            ->authorize(fn () => auth()->user()?->can('update', $order) ?? false)
            ->requiresConfirmation()
            ->action(function () use ($order) {
                PaymentReminderSender::sendManual($order, auth()->user());

                Notification::make()
                    ->title(__('Herinnering verstuurd'))
                    ->success()
                    ->send();
            });
    }
}
