<?php

namespace Qubiqx\QcommerceEcommerceCore\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Qubiqx\QcommerceCore\Models\Customsetting;
use Qubiqx\QcommerceCore\Models\Translation;
use Qubiqx\QcommerceEcommerce\Models\Order;

class OrderCancelledWithCreditMail extends Mailable
{
    use Queueable, SerializesModels;

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
        $invoicePath = storage_path('app/invoices/invoice-' . $this->order->invoice_id . '-' . $this->order->hash . '.pdf');

        $mail = $this->view('qcommerce-ecommerce-core::emails.cancelled-order')
            ->from(Customsetting::get('site_from_email'), Customsetting::get('company_name'))
            ->subject(Translation::get('order-cancelled-email-subject', 'orders', 'Order #:orderId: has been cancelled', 'text', [
                'orderId' => $this->order->parentCreditOrder->invoice_id,
            ]))
            ->with([
                'order' => $this->order,
            ])->attach($invoicePath, [
                'as' => Customsetting::get('company_name') . ' - ' . $this->order->invoice_id . '.pdf',
                'mime' => 'application/pdf',
            ]);

        return $mail;
    }
}
