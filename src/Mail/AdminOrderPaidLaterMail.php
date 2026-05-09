<?php

namespace Dashed\DashedEcommerceCore\Mail;

use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedCore\Notifications\DTOs\TelegramSummary;

class AdminOrderPaidLaterMail extends AdminOrderConfirmationMail
{
    public function __construct(Order $order, public string $previousStatus = '')
    {
        parent::__construct($order);
    }

    public function telegramSummary(): TelegramSummary
    {
        $summary = parent::telegramSummary();

        $title = match ($this->previousStatus) {
            'partially_paid' => 'Restant betaald op bestelling #' . $this->order->invoice_id,
            'waiting_for_confirmation' => 'Bestelling bevestigd & betaald #' . $this->order->invoice_id,
            default => 'Bestelling betaald #' . $this->order->invoice_id,
        };

        $fields = $summary->fields;
        if ($this->previousStatus !== '') {
            $fields['Vorige status'] = $this->previousStatus;
        }

        $createdAt = $this->order->created_at;
        if ($createdAt) {
            $minutes = (int) abs(round($createdAt->diffInMinutes(now())));
            if ($minutes >= 1) {
                if ($minutes >= 60 * 24) {
                    $waited = round($minutes / (60 * 24), 1) . ' dagen';
                } elseif ($minutes >= 60) {
                    $waited = round($minutes / 60, 1) . ' uur';
                } else {
                    $waited = $minutes . ' min';
                }
                $fields['Onbetaald gebleven'] = $waited;
            }
        }

        return new TelegramSummary(
            title: $title,
            fields: $fields,
            adminUrl: $summary->adminUrl,
            emoji: '💶',
        );
    }
}
