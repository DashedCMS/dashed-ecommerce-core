<?php

namespace Dashed\DashedEcommerceCore\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Dashed\DashedCore\Classes\Sites;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedTranslations\Models\Translation;

class ProductListExportMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public string $hash;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(string $hash)
    {
        $this->hash = $hash;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $view = view()->exists(env('SITE_THEME', 'dashed') . '.emails.exported-product-list') ? env('SITE_THEME', 'dashed') . '.emails.exported-product-list' : 'dashed-ecommerce-core::emails.exported-product-list';

        $productListPath = Storage::disk('dashed')->url('dashed/tmp-exports/' . $this->hash . '/product-lists/product-list.xlsx');

        return $this->view($view)
            ->from(Customsetting::get('site_from_email'), Customsetting::get('site_name'))
            ->subject(Translation::get('exported-product-list-email-subject', 'products', 'Exported product list'))
            ->with([
                'logo' => Customsetting::get('site_logo', Sites::getActive(), ''),
            ])
            ->attach($productListPath, [
                'as' => Customsetting::get('site_name') . ' - exported product list.xlsx',
                'mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ]);
    }
}
