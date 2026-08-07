<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Notifications\PrintQueue;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Dashed\DashedEcommerceCore\Models\PrintJob;
use Filament\Notifications\Notification as FilamentNotification;

class PrintJobExhaustedRetriesNotification extends Notification
{
    use Queueable;

    public function __construct(public PrintJob $job)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return FilamentNotification::make()
            ->title(__('Print job opgegeven (order :nummer)', ['nummer' => $this->job->order?->invoice_id]))
            ->body($this->job->error_message ?? __('Geen foutmelding'))
            ->danger()
            ->getDatabaseMessage();
    }
}
