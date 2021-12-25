<?php

namespace Qubiqx\QcommerceEcommerceCore\Classes;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Qubiqx\QcommerceEcommerce\Models\Order;
use Qubiqx\QcommerceEcommerce\Models\OrderLog;
use Qubiqx\QcommerceEcommerceCore\Mail\OrderConfirmationMail;
use Qubiqx\QcommerceEcommerceCore\Mail\PreOrderConfirmationMail;

class Orders
{
    public static function getFulfillmentStatusses()
    {
        return [
            'unhandled' => [
                'name' => 'Niet afgehandeld'
            ],
            'handled' => [
                'name' => 'Afgehandeld'
            ],
            'in_treatment' => [
                'name' => 'In behandeling'
            ],
            'packed' => [
                'name' => 'Ingepakt'
            ],
            'shipped' => [
                'name' => 'Verzonden'
            ]
        ];
    }

    public static function sendNotification(Order $order, $email = null)
    {
        try {
            if ($order->contains_pre_orders) {
                Mail::to($email ?: $order->email)->send(new PreOrderConfirmationMail($order));
            } else {
                Mail::to($email ?: $order->email)->send(new OrderConfirmationMail($order));
            }
            if (app()->runningInConsole()) {
                $orderLog = new OrderLog();
                $orderLog->order_id = $order->id;
                $orderLog->user_id = null;
                $orderLog->tag = 'order.system.paid.invoice.mail.send';
                $orderLog->save();
            } else {
                $orderLog = new OrderLog();
                $orderLog->order_id = $order->id;
                $orderLog->user_id = Auth::check() ? Auth::user()->id : null;
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
