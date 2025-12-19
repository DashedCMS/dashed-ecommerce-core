<?php

namespace Dashed\DashedEcommerceCore\Classes;

use Dashed\DashedCore\Classes\Mails;
use Dashed\DashedCore\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\OrderLog;
use Dashed\DashedEcommerceCore\Mail\OrderConfirmationMail;
use Dashed\DashedEcommerceCore\Mail\PreOrderConfirmationMail;

class Orders
{
    public static function getFulfillmentStatusses()
    {
        return [
            'unhandled' => 'Niet afgehandeld',
            'handled' => 'Afgehandeld',
            'in_treatment' => 'In behandeling',
            'packed' => 'Ingepakt',
            'ready_for_pickup' => 'Klaar om opgehaald te worden',
            'shipped' => 'Verzonden',
        ];
    }

    public static function getReturnStatusses()
    {
        return [
            'handled' => 'Afgehandeld',
            'unhandled' => 'Niet afgehandeld',
            'received' => 'Ontvangen',
            'shipped' => 'Onderweg',
            'waiting_for_return' => 'Wachten op retour',
        ];
    }

    public static function sendNotification(Order $order, ?string $email = null, ?User $mailSendByUser): void
    {
        if (! $email && ! $order->email) {
            return;
        }

        try {
            if ($order->contains_pre_orders) {
                Mail::to($email ?: $order->email)->bcc(Mails::getBCCNotificationEmails())->send(new PreOrderConfirmationMail($order));
            } else {
                Mail::to($email ?: $order->email)->bcc(Mails::getBCCNotificationEmails())->send(new OrderConfirmationMail($order));
            }
            if (app()->runningInConsole() && ! $mailSendByUser) {
                $orderLog = new OrderLog();
                $orderLog->order_id = $order->id;
                $orderLog->user_id = null;
                $orderLog->tag = 'order.system.paid.invoice.mail.send';
                $orderLog->save();
            } else {
                $orderLog = new OrderLog();
                $orderLog->order_id = $order->id;
                $orderLog->user_id = Auth::check() ? Auth::user()->id : ($mailSendByUser->id ?? null);
                $orderLog->tag = 'order.paid.invoice.mail.send';
                $orderLog->save();
            }
        } catch (\Exception $e) {
            if (app()->runningInConsole()) {
                $orderLog = new OrderLog();
                $orderLog->order_id = $order->id;
                $orderLog->user_id = null;
                $orderLog->tag = 'order.system.paid.invoice.mail.send.failed';
                $orderLog->note = 'Error: ' . $e->getMessage();
                $orderLog->save();
            } else {
                $orderLog = new OrderLog();
                $orderLog->order_id = $order->id;
                $orderLog->user_id = Auth::check() ? Auth::user()->id : null;
                $orderLog->tag = 'order.paid.invoice.mail.send.failed';
                $orderLog->note = 'Error: ' . $e->getMessage();
                $orderLog->save();
            }
        }
    }
}
