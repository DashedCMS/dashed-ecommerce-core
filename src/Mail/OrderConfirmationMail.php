<?php

namespace Qubiqx\QcommerceEcommerceCore\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Qubiqx\QcommerceTranslations\Models\Translation;
use Qubiqx\QcommerceCore\Models\Customsetting;
use Qubiqx\QcommerceEcommerceCore\Models\Order;

class OrderConfirmationMail extends Mailable
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
        $invoicePath = storage_path('app/public/qcommerce/invoices/invoice-' . $this->order->invoice_id . '-' . $this->order->hash . '.pdf');

        $mail = $this->view('qcommerce-ecommerce-core::emails.confirm-order')
            ->from(Customsetting::get('site_from_email'), Customsetting::get('company_name'))
            ->subject(Translation::get('order-confirmation-email-subject', 'orders', 'Order confirmation for order #:orderId:', 'text', [
                'orderId' => $this->order->invoice_id,
            ]))
            ->with([
                'order' => $this->order,
            ])->attach($invoicePath, [
                'as' => Customsetting::get('company_name').' - '.$this->order->invoice_id.'.pdf',
                'mime' => 'application/pdf',
            ]);

        $bccEmail = Customsetting::get('checkout_bcc_email', $this->order->site_id);
        if ($bccEmail) {
            $mail->bcc($bccEmail);
        }

        return $mail;
    }
}
