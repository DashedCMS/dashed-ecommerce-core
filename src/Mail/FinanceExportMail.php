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
    public ?string $subjectString = '';
    public ?string $filePath;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(string $hash, ?string $subjectString = '', ?string $filePath = null)
    {
        $this->hash = $hash;
        $this->subjectString = $subjectString;
        $this->filePath = $filePath;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $view = view()->exists(config('dashed-core.site_theme', 'dashed') . '.emails.exported-invoice') ? config('dashed-core.site_theme', 'dashed') . '.emails.exported-invoice' : 'dashed-ecommerce-core::emails.exported-invoice';

        $mail = $this->view($view)
            ->from(Customsetting::get('site_from_email'), Customsetting::get('site_name'))
            ->subject($this->subjectString ?: Translation::get('exported-invoice-email-subject', 'orders', 'Exported invoice'))
            ->with([
                'logo' => Customsetting::get('site_logo', Sites::getActive(), ''),
            ]);

        $relativePath = $this->filePath ?: 'dashed/tmp-exports/' . $this->hash . '/invoices/exported-invoice.pdf';
        $mail->attachFromStorageDisk('public', $relativePath, $this->subjectString ? $this->subjectString . '.pdf' : (Customsetting::get('site_name') . ' - exported invoice.pdf'));

        return $mail;
    }
}
