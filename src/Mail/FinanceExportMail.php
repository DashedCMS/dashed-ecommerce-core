<?php

namespace Dashed\DashedEcommerceCore\Mail;

use Dashed\DashedCore\Classes\Sites;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
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
        $invoicePath = Storage::disk('public')->url('dashed/tmp-exports/' . $this->hash . '/invoices/exported-invoice.pdf');

        return $this->view('dashed-ecommerce-core::emails.exported-invoice')
            ->from(Customsetting::get('site_from_email'), Customsetting::get('company_name'))
            ->subject(Translation::get('exported-invoice-email-subject', 'orders', 'Exported invoice'))
            ->with([
                'logo' => Customsetting::get('site_logo', Sites::getActive(), '')
            ])
            ->attach($invoicePath, [
                'as' => Customsetting::get('company_name') . ' - exported invoice.pdf',
                'mime' => 'application/pdf',
            ]);
    }
}
