<?php

namespace Dashed\DashedEcommerceCore\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Dashed\DashedCore\Classes\Sites;
use Illuminate\Queue\SerializesModels;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedTranslations\Models\Translation;
use Illuminate\Support\Collection;

class OrderConfirmationForFulfillerMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public Order $order;
    public array $orderProducts;
    public bool $sendProductsToCustomer;
    public null|array|Collection $files;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(Order $order, array $orderProducts, bool $sendProductsToCustomer, null|array|Collection $files)
    {
        $this->order = $order;
        $this->orderProducts = $orderProducts;
        $this->sendProductsToCustomer = $sendProductsToCustomer;
        $this->files = $files;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $view = view()->exists(env('SITE_THEME', 'dashed') . '.emails.confirm-order-for-fulfiller') ? env('SITE_THEME', 'dashed') . '.emails.confirm-order-for-fulfiller' : 'dashed-ecommerce-core::emails.confirm-order-for-fulfiller';

        $mail = $this->view($view)
            ->from(Customsetting::get('site_from_email'), Customsetting::get('site_name'))
            ->subject(Translation::get('order-confirmation-for-fulfiller-email-subject', 'orders', 'Bestelling #:orderId: vanuit :siteName:', 'text', [
                'orderId' => $this->order->invoice_id,
                'siteName' => Customsetting::get('site_name'),
            ]))
            ->with([
                'order' => $this->order,
                'orderProducts' => $this->orderProducts,
                'sendProductsToCustomer' => $this->sendProductsToCustomer,
                'logo' => Customsetting::get('site_logo', Sites::getActive(), ''),
            ]);

        foreach ($this->files ?: [] as $file) {
            $media = mediaHelper()->getSingleMedia($file);
            $mail->attachFromStorageDisk('dashed', $media->path);
        }

        return $mail;
    }
}
