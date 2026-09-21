<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources\OrderResource\Actions;

use Filament\Actions\Action;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\OrderLog;

class TogglePaymentRemindersAction
{
    public static function make(Order $order): Action
    {
        return Action::make('togglePaymentReminders')
            ->label(fn () => $order->payment_reminders_paused_at ? __('Herinneringen hervatten') : __('Herinneringen pauzeren'))
            ->icon('heroicon-o-pause-circle')
            ->visible(fn () => $order->payment_due_at && $order->outstandingAmount() > 0)
            ->authorize(fn () => auth()->user()?->can('update', $order) ?? false)
            ->action(function () use ($order) {
                $paused = ! $order->payment_reminders_paused_at;
                $order->forceFill(['payment_reminders_paused_at' => $paused ? now() : null])->save();

                OrderLog::createLog(orderId: $order->id, tag: $paused ? 'order.payment-reminders.paused' : 'order.payment-reminders.resumed');
            });
    }
}
