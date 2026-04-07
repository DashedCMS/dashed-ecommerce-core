<?php

namespace Dashed\DashedEcommerceCore\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Dashed\DashedCore\Classes\Sites;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Models\Cart;
use Dashed\DashedEcommerceCore\Models\AbandonedCartEmail;
use Dashed\DashedEcommerceCore\Models\DiscountCode;

class AbandonedCartEmail3Mail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Cart $cart,
        public readonly AbandonedCartEmail $abandonedCartEmail,
        public readonly ?DiscountCode $discountCode = null,
    ) {
    }

    public function build(): static
    {
        $siteName = Customsetting::get('site_name', Sites::getActive(), config('app.name'));
        $fromEmail = Customsetting::get('site_from_email', Sites::getActive());
        $subject = Customsetting::get('abandoned_cart_email3_subject', null, 'Speciaal voor jou: een cadeautje');

        $view = view()->exists(config('dashed-core.site_theme', 'dashed') . '.emails.abandoned-cart-3')
            ? config('dashed-core.site_theme', 'dashed') . '.emails.abandoned-cart-3'
            : 'dashed-ecommerce-core::emails.abandoned-cart-3';

        $checkoutUrl = url('/checkout');
        if ($this->discountCode) {
            $checkoutUrl = url('/checkout') . '?discount=' . $this->discountCode->code;
        }

        return $this
            ->view($view)
            ->from($fromEmail, $siteName)
            ->subject($subject)
            ->with([
                'cart' => $this->cart,
                'siteName' => $siteName,
                'logo' => Customsetting::get('site_logo', Sites::getActive(), ''),
                'checkoutUrl' => $checkoutUrl,
                'discountCode' => $this->discountCode,
            ]);
    }
}
