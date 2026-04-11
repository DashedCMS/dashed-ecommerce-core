<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources\OrderResource\Actions;

use Filament\Actions\Action;
use Illuminate\Support\Facades\Storage;
use Filament\Notifications\Notification;
use Dashed\DashedEcommerceCore\Models\Order;

class RegenerateInvoiceAction
{
    public static function make(Order $order): Action
    {
        return Action::make('regenerateInvoice')
            ->label(__('Factuur regenereren'))
            ->icon('heroicon-o-arrow-path')
            ->button()
            ->requiresConfirmation()
            ->visible(fn () => ! empty($order->invoice_id) && ! in_array($order->invoice_id, ['PROFORMA', 'RETURN'], true))
            ->action(function () use ($order) {
                (new self())->handle($order);

                Notification::make()
                    ->title(__('Factuur opnieuw gegenereerd'))
                    ->success()
                    ->send();
            });
    }

    public function handle(Order $order): void
    {
        if (empty($order->invoice_id) || in_array($order->invoice_id, ['PROFORMA', 'RETURN'], true)) {
            return;
        }

        $invoicePath = '/dashed/invoices/invoice-' . $order->invoice_id . '-' . $order->hash . '.pdf';
        if (Storage::disk('dashed')->exists($invoicePath)) {
            Storage::disk('dashed')->delete($invoicePath);
        }

        $order->createNormalInvoice();
    }
}
