<?php

namespace Dashed\DashedEcommerceCore\Listeners;

use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\Log;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\OrderLog;

/**
 * Logt elke verzonden order-mail bij de order. De order wordt afgeleid uit de
 * publieke properties van de mailable (buildViewData): direct een Order, of via
 * een relatie-property (orderReturn/trackAndTrace) met een ->order. Mails die
 * niet aan een order te koppelen zijn worden overgeslagen.
 */
class LogSentOrderMail
{
    public function handle(MessageSent $event): void
    {
        // Loggen mag nooit de verzending laten falen: de mail is op dit punt al
        // verstuurd, dus een fout hier vangen we af en loggen we naar de log-file.
        try {
            $order = $this->resolveOrder($event->data);

            if (! $order) {
                return;
            }

            $subject = (string) ($event->message->getSubject() ?? '');
            $recipients = collect($event->message->getTo())
                ->map(fn ($address) => $address->getAddress())
                ->implode(', ');

            $log = new OrderLog();
            $log->order_id = $order->id;
            $log->user_id = auth()->check() ? auth()->id() : null;
            $log->tag = 'order.email.sent';
            $log->email_subject = $subject;
            $log->note = 'Onderwerp: ' . $subject . ' (verzonden naar: ' . $recipients . ')';
            $log->is_system = true;
            $log->public_for_customer = false;
            $log->save();
        } catch (\Throwable $e) {
            Log::error('LogSentOrderMail kon de verzonden mail niet loggen: ' . $e->getMessage());
        }
    }

    protected function resolveOrder(array $data): ?Order
    {
        foreach ($data as $value) {
            if ($value instanceof Order) {
                return $value;
            }
        }

        foreach ($data as $value) {
            if (is_object($value) && isset($value->order) && $value->order instanceof Order) {
                return $value->order;
            }
        }

        return null;
    }
}
