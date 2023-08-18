<?php

namespace Dashed\DashedEcommerceCore\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedTranslations\Models\Translation;

class OrderCancelledWithCreditMail extends Mailable
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
        $invoicePath = storage_path('app/public/dashed/invoices/invoice-' . $this->order->invoice_id . '-' . $this->order->hash . '.pdf');

        $mail = $this->view('dashed-ecommerce-core::emails.cancelled-order')
            ->from(Customsetting::get('site_from_email'), Customsetting::get('company_name'))
            ->subject(Translation::get('order-cancelled-email-subject', 'orders', 'Order #:orderId: has been cancelled', 'text', [
                'orderId' => $this->order->parentCreditOrder->invoice_id,
            ]))
            ->with([
                'order' => $this->order,
            ])->attachFromStorageDisk('dashed', $invoicePath, null, [
                'as' => Customsetting::get('company_name') . ' - ' . $this->order->invoice_id . '.pdf',
                'mime' => 'application/pdf',
            ]);

        return $mail;
    }
}
