<?php

namespace Dashed\DashedEcommerceCore\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Dashed\DashedCore\Classes\Sites;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedTranslations\Models\Translation;

class OrderListExportMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public string $hash;
    public ?string $filePath;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(string $hash, ?string $filePath = null)
    {
        $this->hash = $hash;
        $this->filePath = $filePath;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $relativePath = $this->filePath ?: 'dashed/tmp-exports/' . $this->hash . '/order-lists/order-list.xlsx';

        $view = view()->exists(config('dashed-core.site_theme', 'dashed') . '.emails.exported-order-list') ? config('dashed-core.site_theme', 'dashed') . '.emails.exported-order-list' : 'dashed-ecommerce-core::emails.exported-order-list';

        return $this->view($view)
            ->from(Customsetting::get('site_from_email'), Customsetting::get('site_name'))
            ->subject(Translation::get('exported-order-list-email-subject', 'orders', 'Export van bestellingen'))
            ->with([
                'logo' => Customsetting::get('site_logo', Sites::getActive(), ''),
            ])
            ->attachFromStorageDisk('dashed', $relativePath, Customsetting::get('site_name') . ' - export van bestellingen.xlsx', [
                'mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ]);
    }
}
