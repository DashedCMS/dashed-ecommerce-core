<?php

namespace Dashed\DashedEcommerceCore\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedTranslations\Models\Translation;
use Dashed\DashedEcommerceCore\Models\OrderTrackAndTrace;

class TrackandTraceMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public OrderTrackAndTrace $trackAndTrace;

    public function __construct(OrderTrackAndTrace $trackAndTrace)
    {
        $this->trackAndTrace = $trackAndTrace;
    }

    public function build()
    {
        $view = view()->exists(env('SITE_THEME', 'dashed') . '.emails.track-and-trace') ? env('SITE_THEME', 'dashed') . '.emails.track-and-trace' : 'dashed-ecommerce-core::emails.track-and-trace';

        return $this->view($view)
            ->from(Customsetting::get('site_from_email'), Customsetting::get('site_name'))
            ->subject(Translation::get('order-track-and-trace-email-subject', 'track-and-trace', 'Er is een track&trace beschikbaar  voor bestelling :orderId:', 'text', [
                'orderId' => $this->trackAndTrace->order->invoice_id,
            ]))->with([
                'trackAndTrace' => $this->trackAndTrace,
            ]);
    }
}
