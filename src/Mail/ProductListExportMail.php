<?php

namespace Qubiqx\QcommerceEcommerceCore\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Qubiqx\QcommerceCore\Models\Customsetting;
use Qubiqx\QcommerceTranslations\Models\Translation;

class ProductListExportMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct()
    {
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $productListPath = storage_path('app/product-lists/product-list.xlsx');

        return $this->view('qcommerce-ecommerce-core::emails.exported-product-list')
            ->from(Customsetting::get('site_from_email'), Customsetting::get('company_name'))
            ->subject(Translation::get('exported-product-list-email-subject', 'products', 'Exported product list'))
            ->attach($productListPath, [
                'as' => Customsetting::get('company_name') . ' - exported product list.xlsx',
                'mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ]);
    }
}
