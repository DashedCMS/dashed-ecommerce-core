<?php

namespace Dashed\DashedEcommerceCore\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Dashed\DashedCore\Classes\Sites;
use Illuminate\Queue\SerializesModels;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\OrderLog;
use Dashed\DashedTranslations\Models\Translation;

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
        $mail = $this->view(env('SITE_THEME', 'dashed') . '.emails.order-note')
            ->from(Customsetting::get('site_from_email'), Customsetting::get('company_name'))
            ->subject($this->orderLog->email_subject ?: Translation::get('order-note-update-email-subject', 'orders', 'Your order #:orderId: has been updated', 'text', [
                'orderId' => $this->order->invoice_id,
            ]))
            ->with([
                'order' => $this->order,
                'orderLog' => $this->orderLog,
                'logo' => Customsetting::get('site_logo', Sites::getActive(), ''),
            ]);

        foreach ($this->orderLog->images ?: [] as $image) {
            $media = mediaHelper()->getSingleImage($image);
            $mail->attachFromStorageDisk('dashed', $media->path);
        }

        return $mail;
    }
}
