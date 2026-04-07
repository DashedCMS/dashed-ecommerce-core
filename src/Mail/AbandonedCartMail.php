<?php

namespace Dashed\DashedEcommerceCore\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Crypt;
use Dashed\DashedCore\Classes\Sites;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Models\Cart;
use Dashed\DashedEcommerceCore\Models\DiscountCode;
use Dashed\DashedEcommerceCore\Models\AbandonedCartFlowStep;

class AbandonedCartMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Cart $cart,
        public readonly AbandonedCartFlowStep $step,
        public readonly ?DiscountCode $discountCode = null,
        public readonly ?string $stepLocale = null,
    ) {
    }

    public function build(): static
    {
        $locale = $this->stepLocale ?? app()->getLocale();
        $siteName = Customsetting::get('site_name', Sites::getActive(), config('app.name'));
        $fromEmail = Customsetting::get('site_from_email', Sites::getActive());

        $firstItem = $this->cart->items->first();
        $productName = $firstItem?->product?->name ?? $siteName;
        $cartTotal = '€ ' . number_format($this->cart->total, 2, ',', '.');

        $variables = [
            ':siteName:' => $siteName,
            ':product:' => $productName,
            ':cartTotal:' => $cartTotal,
        ];

        $subject = str_replace(array_keys($variables), array_values($variables), $this->step->getTranslation('subject', $locale));

        $blocks = collect($this->step->getTranslation('blocks', $locale) ?? [])->map(function ($block) use ($variables) {
            if ($block['type'] === 'text' && ! empty($block['data']['content'])) {
                $block['data']['content'] = str_replace(array_keys($variables), array_values($variables), $block['data']['content']);
            }
            return $block;
        })->all();

        $review = null;
        $hasReviewBlock = collect($blocks)->contains(fn ($b) => $b['type'] === 'review');
        if ($hasReviewBlock && class_exists(\Dashed\DashedCore\Models\Review::class)) {
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

        $checkoutUrl = url('/restore-cart') . '?cart=' . urlencode(Crypt::encryptString($this->cart->token));
        if ($this->discountCode) {
            $checkoutUrl .= '&discount=' . $this->discountCode->code;
        }

        $view = view()->exists(config('dashed-core.site_theme', 'dashed') . '.emails.abandoned-cart')
            ? config('dashed-core.site_theme', 'dashed') . '.emails.abandoned-cart'
            : 'dashed-ecommerce-core::emails.abandoned-cart';

        return $this
            ->view($view)
            ->from($fromEmail, $siteName)
            ->subject($subject)
            ->with([
                'cart' => $this->cart,
                'step' => $this->step,
                'blocks' => $blocks,
                'siteName' => $siteName,
                'logo' => Customsetting::get('site_logo', Sites::getActive(), ''),
                'checkoutUrl' => $checkoutUrl,
                'discountCode' => $this->discountCode,
                'review' => $review,
            ]);
    }
}
