<?php

namespace Dashed\DashedEcommerceCore\Listeners;

use Dashed\DashedCore\Classes\Mails;
use Dashed\DashedCore\Notifications\AdminNotifier;
use Dashed\DashedEcommerceCore\Classes\OrderOrigins;
use Dashed\DashedEcommerceCore\Events\Orders\OrderMarkedAsPaidEvent;
use Dashed\DashedEcommerceCore\Mail\AdminOrderPaidLaterMail;
use Dashed\DashedEcommerceCore\Models\OrderLog;
use Throwable;

class SendLatePaidAdminNotification
{
    /**
     * Threshold in minutes — only orders that stayed unpaid longer than this
     * are considered "later paid" (typical: POS order paid via betaallink).
     */
    private const LATE_PAYMENT_THRESHOLD_MINUTES = 5;

    public function handle(OrderMarkedAsPaidEvent $event): void
    {
        $order = $event->order;

        // Het event vuurt ook bij partially_paid en waiting_for_confirmation;
        // we willen alleen melden bij volledig betaalde orders.
        if ($order->status !== 'paid') {
            return;
        }

        if (! $order->created_at) {
            return;
        }

        $minutesSinceCreated = (int) abs(round($order->created_at->diffInMinutes(now())));
        if ($minutesSinceCreated < self::LATE_PAYMENT_THRESHOLD_MINUTES) {
            return;
        }

        // If the regular admin-notify path already covers Telegram for this
        // order origin, SendInvoiceJob already fired the standard "new order"
        // Telegram message — skip to avoid duplicates.
        if (OrderOrigins::shouldNotifyAdmin($order->order_origin, 'telegram', $order->site_id)) {
            return;
        }

        try {
            $recipients = Mails::getAdminNotificationEmails();
            $recipients = is_array($recipients) ? $recipients : [];

            // Telegram-only: pass a recipient address so the Mailable is
            // technically sendable, but restrict channels to telegram.
            $to = $recipients !== [] ? $recipients : null;

            AdminNotifier::send(new AdminOrderPaidLaterMail($order), $to, ['telegram']);

            OrderLog::createLog(
                orderId: $order->id,
                tag: 'order.paid-later.telegram-notification.sent',
            );
        } catch (Throwable $e) {
            OrderLog::createLog(
                orderId: $order->id,
                tag: 'order.paid-later.telegram-notification.failed',
                note: 'Error: ' . $e->getMessage(),
            );
        }
    }
}
