<?php

namespace Dashed\DashedEcommerceCore\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Dashed\DashedCore\Classes\Sites;
use Illuminate\Queue\SerializesModels;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedTranslations\Models\Translation;

class FinanceExportMail extends Mailable
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
        $view = view()->exists(env('SITE_THEME', 'dashed') . '.emails.exported-invoice') ? env('SITE_THEME', 'dashed') . '.emails.exported-invoice' : 'dashed-ecommerce-core::emails.exported-invoice';

        $mail = $this->view($view)
            ->from(Customsetting::get('site_from_email'), Customsetting::get('site_name'))
            ->subject(Translation::get('exported-invoice-email-subject', 'orders', 'Exported invoice'))
            ->with([
                'logo' => Customsetting::get('site_logo', Sites::getActive(), ''),
            ]);

        $mail->attachFromStorageDisk('public', 'dashed/tmp-exports/' . $this->hash . '/invoices/exported-invoice.pdf', Customsetting::get('site_name') . ' - exported invoice.pdf');

        return $mail;
    }
}
