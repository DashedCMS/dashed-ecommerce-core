<?php

namespace Qubiqx\QcommerceEcommerceCore\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Qubiqx\QcommerceTranslations\Models\Translation;
use Qubiqx\QcommerceCore\Models\Customsetting;

class OrderListExportMail extends Mailable
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
        $orderListPath = storage_path('app/order-lists/order-list.xlsx');

        return $this->view('qcommerce-ecommerce-core::emails.exported-order-list')
            ->from(Customsetting::get('site_from_email'), Customsetting::get('company_name'))
            ->subject(Translation::get('exported-order-list-email-subject', 'orders', 'Exported order list'))
            ->attach($orderListPath, [
                'as' => Customsetting::get('company_name') . ' - exported order list.xlsx',
                'mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ]);
    }
}
