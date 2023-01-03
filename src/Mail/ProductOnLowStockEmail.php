<?php

namespace Qubiqx\QcommerceEcommerceCore\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Qubiqx\QcommerceCore\Models\Customsetting;
use Qubiqx\QcommerceEcommerceCore\Models\Product;
use Qubiqx\QcommerceTranslations\Models\Translation;

class ProductOnLowStockEmail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public Product $product;

    public function __construct(Product $product)
    {
        $this->product = $product;
    }

    public function build()
    {
        return $this->view('qcommerce-ecommerce-core::emails.product-low-stock')
            ->from(Customsetting::get('site_from_email'), Customsetting::get('company_name'))
            ->subject(Translation::get('product-low-stock-email-subject', 'products', 'Product :productName: low on stock', 'text', [
                'productName' => $this->product->name,
            ]));
    }
}
