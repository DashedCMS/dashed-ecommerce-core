<?php

namespace Dashed\DashedEcommerceCore\Mail;

use Dashed\DashedCore\Notifications\DTOs\TelegramSummary;

class AdminOrderPaidLaterMail extends AdminOrderConfirmationMail
{
    public function telegramSummary(): TelegramSummary
    {
        $summary = parent::telegramSummary();

        $createdAt = $this->order->created_at;
        $waitedFor = null;
        if ($createdAt) {
            $minutes = (int) abs(round($createdAt->diffInMinutes(now())));
            if ($minutes >= 60 * 24) {
                $waitedFor = round($minutes / (60 * 24), 1) . ' dagen';
            } elseif ($minutes >= 60) {
                $waitedFor = round($minutes / 60, 1) . ' uur';
            } else {
                $waitedFor = $minutes . ' min';
            }
        }

        $fields = $summary->fields;
        if ($waitedFor !== null) {
            $fields['Onbetaald gebleven'] = $waitedFor;
        }

        return new TelegramSummary(
            title: 'Bestelling alsnog betaald #' . $this->order->invoice_id,
            fields: $fields,
            adminUrl: $summary->adminUrl,
            emoji: '💶',
        );
    }
}
