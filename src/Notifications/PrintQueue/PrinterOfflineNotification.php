<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Notifications\PrintQueue;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Dashed\DashedEcommerceCore\Models\Printer;
use Filament\Notifications\Notification as FilamentNotification;

class PrinterOfflineNotification extends Notification
{
    use Queueable;

    public function __construct(public Printer $printer)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return FilamentNotification::make()
            ->title('Printer offline')
            ->body("Printer {$this->printer->name} heeft >5 minuten geen ping gestuurd.")
            ->danger()
            ->getDatabaseMessage();
    }
}
