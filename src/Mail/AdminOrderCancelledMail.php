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

class AdminOrderCancelledMail extends Mailable
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

        $view = view()->exists(env('SITE_THEME', 'dashed') . '.emails.admin-cancelled-order') ? env('SITE_THEME', 'dashed') . '.emails.admin-cancelled-order' : 'dashed-ecommerce-core::emails.admin-cancelled-order';

        return $this->view($view)
            ->from(Customsetting::get('site_from_email'), Customsetting::get('site_name'))->subject(Translation::get('admin-order-cancelled-email-subject', 'orders', 'Order #:orderId: cancelled', 'text', [
                'orderId' => $this->order->parentCreditOrder->invoice_id,
            ]))
            ->with([
                'order' => $this->order,
                'logo' => Customsetting::get('site_logo', Sites::getActive(), ''),
            ])->attach($invoicePath, [
                'as' => Customsetting::get('site_name').' - '.$this->order->invoice_id.'.pdf',
                'mime' => 'application/pdf',
            ]);
    }
}
