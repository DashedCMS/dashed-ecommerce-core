<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Dashed\DashedEcommerceCore\Models\Quote;
use Filament\Notifications\Notification as FilamentNotification;

class QuoteAnsweredNotification extends Notification
{
    use Queueable;

    public function __construct(public Quote $quote)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $accepted = $this->quote->status === Quote::STATUS_ACCEPTED;

        return FilamentNotification::make()
            ->title($accepted ? __('Offerte geaccepteerd') : __('Offerte afgewezen'))
            ->body(__('Offerte :nummer van :klant', [
                'nummer' => $this->quote->displayNumber(),
                'klant' => $this->quote->company_name ?: $this->quote->fullName() ?: $this->quote->email,
            ]))
            ->{$accepted ? 'success' : 'warning'}()
            ->getDatabaseMessage();
    }
}
