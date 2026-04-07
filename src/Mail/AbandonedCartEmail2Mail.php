<?php

namespace Dashed\DashedEcommerceCore\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Dashed\DashedCore\Classes\Sites;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Models\Cart;
use Dashed\DashedEcommerceCore\Models\AbandonedCartEmail;

class AbandonedCartEmail2Mail extends Mailable
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

        $firstItem = $this->cart->items->first();
        $productName = $firstItem?->product?->name ?? $siteName;

        $subjectTemplate = Customsetting::get('abandoned_cart_email2_subject', null, 'Je :product wacht nog op je');
        $subject = str_replace(':product', $productName, $subjectTemplate);

        $review = null;
        if (class_exists(\Dashed\DashedCore\Models\Review::class)) {
            $review = \Dashed\DashedCore\Models\Review::query()
                ->where('stars', 5)
                ->whereNotNull('review')
                ->inRandomOrder()
                ->first()
                ?? \Dashed\DashedCore\Models\Review::query()
                    ->whereNotNull('review')
                    ->orderByDesc('stars')
                    ->first();
        }

        $view = view()->exists(config('dashed-core.site_theme', 'dashed') . '.emails.abandoned-cart-2')
            ? config('dashed-core.site_theme', 'dashed') . '.emails.abandoned-cart-2'
            : 'dashed-ecommerce-core::emails.abandoned-cart-2';

        return $this
            ->view($view)
            ->from($fromEmail, $siteName)
            ->subject($subject)
            ->with([
                'cart' => $this->cart,
                'siteName' => $siteName,
                'logo' => Customsetting::get('site_logo', Sites::getActive(), ''),
                'checkoutUrl' => url('/checkout'),
                'review' => $review,
                'productName' => $productName,
            ]);
    }
}
