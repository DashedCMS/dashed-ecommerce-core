<?php

namespace Dashed\DashedEcommerceCore\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Dashed\DashedCore\Classes\Sites;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedTranslations\Models\Translation;

class PreOrderConfirmationMail extends Mailable
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
        $view = view()->exists(config('dashed-core.site_theme') . '.emails.confirm-pre-order') ? config('dashed-core.site_theme') . '.emails.confirm-pre-order' : 'dashed-ecommerce-core::emails.confirm-pre-order';

        $invoicePath = Storage::disk('dashed')->url('dashed/invoices/invoice-' . $this->order->invoice_id . '-' . $this->order->hash . '.pdf');

        $mail = $this->view($view)
            ->from(Customsetting::get('site_from_email'), Customsetting::get('site_name'))
            ->subject(Translation::get('pre-order-confirmation-email-subject', 'pre-orders', 'Pre order confirmation for order #:orderId:', 'text', [
                'orderId' => $this->order->invoice_id,
            ]))
            ->with([
                'order' => $this->order,
                'logo' => Customsetting::get('site_logo', Sites::getActive(), ''),
            ])->attach($invoicePath, [
                'as' => Customsetting::get('site_name').' - '.$this->order->invoice_id.'.pdf',
                'mime' => 'application/pdf',
            ]);

        $bccEmail = Customsetting::get('checkout_bcc_email', $this->order->site_id);
        if ($bccEmail) {
            $mail->bcc($bccEmail);
        }

        return $mail;
    }
}
