<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Services\ProductReviews;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Mail;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\OrderLog;
use Dashed\DashedEcommerceCore\Models\OrderProduct;
use Dashed\DashedEcommerceCore\Models\ProductReview;
use Dashed\DashedEcommerceCore\Models\ProductReviewRequest;
use Dashed\DashedEcommerceCore\Mail\ProductReviewRequestMail;

/**
 * Verstuurt de reviewverzoeken waarvan de wachttijd voorbij is. Elke
 * voorwaarde wordt hier opnieuw getoetst, want tussen inplannen en
 * versturen kan de instelling uit zijn gezet, de bestelling geannuleerd of
 * de klant afgemeld. Een verzoek dat niet weg mag krijgt een reden en komt
 * nooit meer langs.
 */
class ReviewRequestSender
{
    public const MAX_PRODUCTS = 10;

    /**
     * @return array{sent: int, skipped: int}
     */
    public static function sendDue(int $limit = 200): array
    {
        $sent = 0;
        $skipped = 0;

        ProductReviewRequest::query()
            ->whereNull('sent_at')
            ->whereNull('skipped_reason')
            ->where('due_at', '<=', now())
            ->orderBy('due_at')
            ->limit($limit)
            ->with('order')
            ->get()
            ->each(function (ProductReviewRequest $request) use (&$sent, &$skipped): void {
                $reason = self::send($request);
                $reason === null ? $sent++ : $skipped++;
            });

        return ['sent' => $sent, 'skipped' => $skipped];
    }

    /**
     * Geeft null als de mail weg is, anders de reden waarom niet.
     */
    public static function send(ProductReviewRequest $request): ?string
    {
        $order = $request->order;
        $reason = self::refusal($order);
        $lines = $reason === null ? self::reviewableLines($order) : collect();

        if ($reason === null && $lines->isEmpty()) {
            $reason = 'no_products';
        }

        if ($reason === null) {
            try {
                Mail::to($order->email)
                    ->locale($order->locale ?: app()->getLocale())
                    ->send(new ProductReviewRequestMail($order, $lines->all()));
            } catch (\Throwable $e) {
                report($e);

                // Volgende run opnieuw; geen reden zetten.
                return 'mail_failed';
            }

            $request->forceFill(['sent_at' => now()])->save();
            rescue(fn () => OrderLog::createLog($order->getKey(), 'order.product-review-request.sent', __('Reviewverzoek voor :aantal product(en) verstuurd naar de klant', ['aantal' => $lines->count()])), report: false);

            return null;
        }

        $request->forceFill(['skipped_reason' => $reason])->save();

        return $reason;
    }

    public static function refusal(?Order $order): ?string
    {
        if (! $order) {
            return 'order_missing';
        }

        if (! ReviewRequestScheduler::eligible($order)) {
            return 'not_eligible';
        }

        // Afgemeld via de afmeldlink van de opvolgmails (ook die in dit verzoek).
        if ($order->handled_flow_cancelled_at !== null) {
            return 'unsubscribed';
        }

        if (class_exists(\Dashed\DashedNewsletter\Models\NewsletterSuppression::class)
            && rescue(fn () => \Dashed\DashedNewsletter\Models\NewsletterSuppression::blocks((string) $order->site_id, (string) $order->email), false, false)) {
            return 'suppressed';
        }

        return null;
    }

    /**
     * Elk gekocht product één keer, alleen bestaande producten en alleen
     * regels die nog geen review hebben.
     *
     * @return Collection<int, OrderProduct>
     */
    public static function reviewableLines(Order $order): Collection
    {
        $lines = $order->orderProducts()
            ->whereNotNull('product_id')
            ->with('product')
            ->get()
            ->filter(fn (OrderProduct $line) => $line->product && ! $line->product->trashed());

        $reviewed = ProductReview::query()
            ->whereIn('order_product_id', $lines->pluck('id'))
            ->pluck('order_product_id')
            ->all();

        return $lines
            ->reject(fn (OrderProduct $line) => in_array($line->id, $reviewed, true))
            ->unique('product_id')
            ->take(self::MAX_PRODUCTS)
            ->values();
    }

    public static function reviewUrl(OrderProduct $line): string
    {
        return URL::temporarySignedRoute(
            'dashed.frontend.product-review',
            now()->addDays(ProductReviewSettings::linkValidDays()),
            ['orderProduct' => $line->getKey()],
        );
    }
}
