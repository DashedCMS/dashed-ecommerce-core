<?php

namespace Qubiqx\QcommerceEcommerceCore\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Qubiqx\QcommerceCore\Classes\OrderVariableReplacer;
use Qubiqx\QcommerceCore\Models\Customsetting;
use Qubiqx\QcommerceEcommerceCore\Models\Order;

class OrderFulfillmentStatusChangedMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(Order $order, string $subject, string $notification)
    {
        $this->notification = OrderVariableReplacer::handle($order, $notification);
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
