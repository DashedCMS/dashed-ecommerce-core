<?php

namespace Qubiqx\QcommerceEcommerceCore\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Qubiqx\QcommerceCore\Models\Translation;
use Qubiqx\QcommerceCore\Models\Customsetting;

class FinanceExportMail extends Mailable
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
        $invoicePath = storage_path('app/invoices/exported-invoice.pdf');

        return $this->view('qcommerce-ecommerce-core::emails.exported-invoice')
            ->from(Customsetting::get('site_from_email'), Customsetting::get('company_name'))
            ->subject(Translation::get('exported-invoice-email-subject', 'orders', 'Exported invoice'))
            ->attach($invoicePath, [
                'as' => Customsetting::get('company_name') . ' - exported invoice.pdf',
                'mime' => 'application/pdf',
            ]);
    }
}
