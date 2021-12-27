<?php

namespace Qubiqx\QcommerceEcommerceCore\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Qubiqx\QcommerceCore\Models\Translation;
use Qubiqx\QcommerceCore\Models\Customsetting;

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
        return $this->view('qcommerce-ecommerce-core::emails.products-with-past-due-pre-order-date')->from(Customsetting::get('site_from_email'), Customsetting::get('company_name'))->subject(Translation::get('products-with-past-due-pre-order-date-email-subject', 'products-with-past-due-pre-order-date', 'There are products that require attention'))->with([
            'products' => $this->products,
        ]);
    }
}
