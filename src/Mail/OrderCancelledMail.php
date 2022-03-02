<?php

namespace Qubiqx\QcommerceEcommerceCore\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Qubiqx\QcommerceTranslations\Models\Translation;
use Qubiqx\QcommerceCore\Models\Customsetting;
use Qubiqx\QcommerceEcommerceCore\Models\Order;

class OrderCancelledMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(Order $order)
    {
        $this->order = $order;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $mail = $this->view('qcommerce-ecommerce-core::emails.cancelled-order')
            ->from(Customsetting::get('site_from_email'), Customsetting::get('company_name'))
            ->subject(Translation::get('order-cancelled-email-subject', 'orders', 'Order #:orderId: has been cancelled', 'text', [
                'orderId' => $this->order->parentCreditOrder->invoice_id,
            ]))
            ->with([
                'order' => $this->order,
            ]);

        return $mail;
    }
}
