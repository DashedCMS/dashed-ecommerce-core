<?php

namespace Qubiqx\QcommerceEcommerceCore\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Qubiqx\QcommerceCore\Models\Customsetting;
use Qubiqx\QcommerceEcommerceCore\Models\Order;
use Qubiqx\QcommerceEcommerceCore\Classes\OrderVariableReplacer;

class OrderFulfillmentStatusChangedMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(Order $order, string $subject, string $notification)
    {
        $this->notification = OrderVariableReplacer::handle($order, $notification);
        $this->subject = $subject;
        $this->order = $order;
    }

    public function build()
    {
        $notification = $this->notification;

        $variables = [
            'first_name',
            'last_name',
            'email',
            'phone_number',
            'street',
            'house_nr',
            'zip_code',
            'city',
            'country',
            'company_name',
            'total',
        ];

        foreach ($variables as $variable) {
            $notification = str($notification)->replace(":" . str($variable)->camel() . ":", $this->order[$variable]);
        }

        return $this->view('qcommerce-core::emails.notification')
            ->from(Customsetting::get('site_from_email'), Customsetting::get('company_name'))
            ->subject($this->subject)
            ->with([
                'notification' => $notification,
            ]);
    }
}
