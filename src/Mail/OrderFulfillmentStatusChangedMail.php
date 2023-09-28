<?php

namespace Dashed\DashedEcommerceCore\Mail;

use Dashed\DashedCore\Classes\Sites;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Classes\OrderVariableReplacer;

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
        $subject = $this->subject;

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
            $subject = str($subject)->replace(":" . str($variable)->camel() . ":", $this->order[$variable]);
        }

        return $this->view('dashed-core::emails.notification')
            ->from(Customsetting::get('site_from_email'), Customsetting::get('company_name'))
            ->subject($subject)
            ->with([
                'logo' => Customsetting::get('site_logo', Sites::getActive(), ''),
                'notification' => $notification,
            ]);
    }
}
