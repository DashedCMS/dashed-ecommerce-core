<?php

namespace Dashed\DashedEcommerceCore\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Dashed\DashedCore\Classes\Sites;
use Illuminate\Queue\SerializesModels;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Models\DiscountCode;
use Dashed\DashedEcommerceCore\Models\AbandonedCartEmail;
use Dashed\DashedEcommerceCore\Models\AbandonedCartFlowStep;
use Dashed\DashedEcommerceCore\Services\AbandonedCart\AbandonedCartSourceResolver;

class AbandonedCartMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly AbandonedCartEmail $record,
        public readonly AbandonedCartFlowStep $step,
        public readonly ?DiscountCode $discountCode = null,
        public readonly ?string $stepLocale = null,
        public readonly ?int $abandonedCartEmailId = null,
    ) {
    }

    public function build(): static
    {
        $locale = $this->stepLocale ?? app()->getLocale();
        $siteName = Customsetting::get('site_name', Sites::getActive(), config('app.name'));
        $fromEmail = Customsetting::get('site_from_email', Sites::getActive());

        $source = AbandonedCartSourceResolver::for($this->record);

        $items = $source->items();
        $firstItem = $items->first();
        $productName = $firstItem['name'] ?? $siteName;
        $totalCents = $source->total();
        $cartTotal = '€ ' . number_format($totalCents / 100, 2, ',', '.');

        $variables = array_merge([
            ':siteName:' => $siteName,
            ':product:' => $productName,
            ':cartTotal:' => $cartTotal,
            ':orderId:' => '',
            ':orderDate:' => '',
        ], $source->variables());

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

        $resumeUrl = $source->resumeUrl();
        if ($this->discountCode) {
            $sep = str_contains($resumeUrl, '?') ? '&' : '?';
            $resumeUrl .= $sep . 'discount=' . $this->discountCode->code;
        }

        $view = view()->exists(config('dashed-core.site_theme', 'dashed') . '.emails.abandoned-cart')
            ? config('dashed-core.site_theme', 'dashed') . '.emails.abandoned-cart'
            : 'dashed-ecommerce-core::emails.abandoned-cart';

        return $this
            ->view($view)
            ->from($fromEmail, $siteName)
            ->subject($subject)
            ->with([
                'step' => $this->step,
                'blocks' => $blocks,
                'items' => $items,
                'total' => $totalCents,
                'totalFormatted' => $cartTotal,
                'resumeUrl' => $resumeUrl,
                'productUrl' => $resumeUrl,
                'checkoutUrl' => $resumeUrl,
                'siteName' => $siteName,
                'logo' => Customsetting::get('site_logo', Sites::getActive(), ''),
                'discountCode' => $this->discountCode,
                'review' => $review,
            ]);
    }
}
