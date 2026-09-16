<?php

namespace Dashed\DashedEcommerceCore\Classes;

use Dashed\DashedCore\Models\User;
use Dashed\DashedCore\Classes\Mails;
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
            'returned' => 'Geretourneerd',
            'partially_returned' => 'Deels geretourneerd',
        ];
    }

    /**
     * Stuurt de bevestigingsmail en zegt of dat gelukt is. Een fout wordt
     * gemeld bij de foutmelder en komt met bestand en regel in het
     * orderlogboek; alleen de melding was niet genoeg om een "array offset
     * on null" ooit terug te vinden. Er wordt niets doorgegooid, want een
     * mislukte mail mag een betaling of een statusovergang nooit tegenhouden.
     */
    public static function sendNotification(Order $order, ?string $email = null, ?User $mailSendByUser = null): bool
    {
        if (! $email && ! $order->email) {
            return false;
        }

        try {
            if ($order->contains_pre_orders) {
                Mail::to($email ?: $order->email)->bcc(Mails::getBCCNotificationEmails())->send(new PreOrderConfirmationMail($order));
            } else {
                Mail::to($email ?: $order->email)->bcc(Mails::getBCCNotificationEmails())->send(new OrderConfirmationMail($order));
            }

            return true;
        } catch (\Throwable $e) {
            report($e);

            $orderLog = new OrderLog();
            $orderLog->order_id = $order->id;
            $orderLog->user_id = app()->runningInConsole() ? null : (Auth::check() ? Auth::user()->id : null);
            $orderLog->tag = app()->runningInConsole() ? 'order.system.paid.invoice.mail.send.failed' : 'order.paid.invoice.mail.send.failed';
            $orderLog->note = 'Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine();
            $orderLog->save();

            return false;
        }
    }
}
