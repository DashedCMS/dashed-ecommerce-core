<?php

namespace Dashed\DashedEcommerceCore\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Dashed\DashedCore\Classes\Sites;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Models\Cart;
use Dashed\DashedEcommerceCore\Models\AbandonedCartEmail;

class AbandonedCartEmail1Mail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Cart $cart,
        public readonly AbandonedCartEmail $abandonedCartEmail,
    ) {
    }

    public function build(): static
    {
        $siteName = Customsetting::get('site_name', Sites::getActive(), config('app.name'));
        $fromEmail = Customsetting::get('site_from_email', Sites::getActive());
        $subject = Customsetting::get('abandoned_cart_email1_subject', null, 'Je hebt iets achtergelaten');

        $view = view()->exists(config('dashed-core.site_theme', 'dashed') . '.emails.abandoned-cart-1')
            ? config('dashed-core.site_theme', 'dashed') . '.emails.abandoned-cart-1'
            : 'dashed-ecommerce-core::emails.abandoned-cart-1';

        return $this
            ->view($view)
            ->from($fromEmail, $siteName)
            ->subject($subject)
            ->with([
                'cart' => $this->cart,
                'siteName' => $siteName,
                'logo' => Customsetting::get('site_logo', Sites::getActive(), ''),
                'checkoutUrl' => url('/checkout'),
            ]);
    }
}
