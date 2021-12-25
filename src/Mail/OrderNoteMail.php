<?php

namespace Qubiqx\QcommerceEcommerceCore\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Qubiqx\QcommerceEcommerce\Models\Order;
use Qubiqx\QcommerceCore\Models\Translation;
use Qubiqx\QcommerceCore\Models\Customsetting;
use Qubiqx\QcommerceEcommerce\Models\OrderLog;

class OrderNoteMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(Order $order, OrderLog $orderLog)
    {
        $this->order = $order;
        $this->orderLog = $orderLog;
    }

    public function build()
    {
        return $this->view('qcommerce-ecommerce-core::emails.order-note')->from(Customsetting::get('site_from_email'), Customsetting::get('company_name'))->subject(Translation::get('order-note-update-email-subject', 'orders', 'Your order #:orderId: has been updated', 'text', [
            'orderId' => $this->order->invoice_id,
        ]))->with([
            'order' => $this->order,
            'orderLog' => $this->orderLog,
        ]);
    }
}
