<?php

namespace Dashed\DashedEcommerceCore\Listeners;

use Throwable;
use Dashed\DashedCore\Classes\Mails;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\OrderLog;
use Dashed\DashedCore\Notifications\AdminNotifier;
use Dashed\DashedEcommerceCore\Classes\OrderOrigins;
use Dashed\DashedEcommerceCore\Mail\AdminOrderPaidLaterMail;

/**
 * Eloquent observer voor Order. Vuurt een Telegram melding wanneer een order
 * naar `paid` overgaat:
 *  - pending/concept → paid (mits SendInvoiceJob's standaard admin-notify niet
 *    al voor dezelfde origin/channel actief is, om dubbele meldingen te
 *    voorkomen voor 'own'/'Bol' enz.)
 *  - partially_paid → paid (Order::markAsPaid()'s eerste branch dispatcht
 *    geen `OrderMarkedAsPaidEvent`, dus zonder deze observer zou er niets
 *    afgaan)
 *  - waiting_for_confirmation → paid (idem)
 */
class SendLatePaidAdminNotification
{
    public function saved(Order $order): void
    {
        if (! $order->wasChanged('status')) {
            return;
        }

        if ($order->status !== 'paid') {
            return;
        }

        $previousStatus = $order->getOriginal('status');
        if ($previousStatus === 'paid') {
            return;
        }

        // Voor de pending/concept → paid transitie verzorgt SendInvoiceJob
        // (vanuit Order::markAsPaid()'s else-branch) al de admin Telegram-
        // melding wanneer de origin op Telegram-notify staat. In dat geval
        // hier overslaan om dubbele meldingen te voorkomen.
        if (in_array($previousStatus, ['pending', 'concept', null], true)
            && OrderOrigins::shouldNotifyAdmin($order->order_origin, 'telegram', $order->site_id)) {
            return;
        }

        try {
            $recipients = Mails::getAdminNotificationEmails();
            $recipients = is_array($recipients) ? $recipients : [];
            $to = $recipients !== [] ? $recipients : null;

            AdminNotifier::send(
                new AdminOrderPaidLaterMail($order, (string) $previousStatus),
                $to,
                ['telegram'],
            );

            OrderLog::createLog(
                orderId: $order->id,
                tag: 'order.paid.telegram-notification.sent',
                note: 'Vorige status: ' . ($previousStatus ?? 'null'),
            );
        } catch (Throwable $e) {
            OrderLog::createLog(
                orderId: $order->id,
                tag: 'order.paid.telegram-notification.failed',
                note: 'Error: ' . $e->getMessage(),
            );
        }
    }
}
