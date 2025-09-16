<?php

namespace Dashed\DashedEcommerceCore\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Dashed\DashedCore\Classes\Sites;
use Illuminate\Queue\SerializesModels;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedTranslations\Models\Translation;

class ProductsWithPastDuePreOrderDateMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct($products)
    {
        $this->products = $products;
    }

    public function build()
    {
        $view = view()->exists(config('dashed-core.site_theme') . '.emails.products-with-past-due-pre-order-date') ? config('dashed-core.site_theme') . '.emails.products-with-past-due-pre-order-date' : 'dashed-ecommerce-core::emails.products-with-past-due-pre-order-date';

        return $this->view($view)
            ->from(Customsetting::get('site_from_email'), Customsetting::get('site_name'))
            ->subject(Translation::get('products-with-past-due-pre-order-date-email-subject', 'products-with-past-due-pre-order-date', 'There are products that require attention'))
            ->with([
                'products' => $this->products,
                'logo' => Customsetting::get('site_logo', Sites::getActive(), ''),
            ]);
    }
}
