<?php

namespace Dashed\DashedEcommerceCore\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Dashed\DashedCore\Classes\Sites;
use Illuminate\Queue\SerializesModels;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedTranslations\Models\Translation;

class FinanceReportMail extends Mailable
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
        $view = view()->exists(config('dashed-core.site_theme') . '.emails.exported-invoice') ? config('dashed-core.site_theme') . '.emails.exported-invoice' : 'dashed-ecommerce-core::emails.exported-invoice';

        $mail = $this->view($view)
            ->from(Customsetting::get('site_from_email'), Customsetting::get('site_name'))
            ->subject(Translation::get('exported-finance-report-email-subject', 'finance-report', 'Exported finance report'))
            ->with([
                'logo' => Customsetting::get('site_logo', Sites::getActive(), ''),
            ]);

        $mail->attachFromStorageDisk('public', 'dashed/tmp-exports/' . $this->hash . '/financial-reports/financial-report.pdf', Customsetting::get('site_name') . ' - exported finance report.pdf');

        return $mail;
    }
}
