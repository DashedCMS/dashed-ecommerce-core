<?php

namespace Dashed\DashedEcommerceCore\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Dashed\DashedCore\Classes\Sites;
use Illuminate\Support\Facades\View;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedTranslations\Models\Translation;

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
        $invoicePath = Storage::disk('dashed')->url('dashed/invoices/invoice-' . $this->order->invoice_id . '-' . $this->order->hash . '.pdf');

        if (View::exists('dashed.emails.confirm-order')) {
            $view = 'dashed.emails.confirm-order';
        } else {
            $view = 'dashed-ecommerce-core::emails.confirm-order';
        }

        $mail = $this->view($view)
            ->from(Customsetting::get('site_from_email'), Customsetting::get('company_name'))
            ->subject(Translation::get('order-confirmation-email-subject', 'orders', 'Order confirmation for order #:orderId:', 'text', [
                'orderId' => $this->order->invoice_id,
            ]))
            ->with([
                'order' => $this->order,
                'logo' => Customsetting::get('site_logo', Sites::getActive(), ''),
            ])->attach($invoicePath, [
                'as' => Customsetting::get('company_name') . ' - ' . $this->order->invoice_id . '.pdf',
                'mime' => 'application/pdf',
            ]);

        $bccEmail = Customsetting::get('checkout_bcc_email', $this->order->site_id);
        if ($bccEmail) {
            $mail->bcc($bccEmail);
        }

        return $mail;
    }
}
