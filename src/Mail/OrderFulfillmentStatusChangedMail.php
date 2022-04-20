<?php

namespace Qubiqx\QcommerceEcommerceCore\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Qubiqx\QcommerceCore\Models\Customsetting;

class OrderFulfillmentStatusChangedMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(string $subject, string $notification)
    {
        $this->notification = $notification;
        $this->subject = $subject;
    }

    public function build()
    {
        return $this->view('qcommerce-core::emails.notification')
            ->from(Customsetting::get('site_from_email'), Customsetting::get('company_name'))
            ->subject($this->subject)
            ->with([
            'notification' => $this->notification,
        ]);
    }
}
