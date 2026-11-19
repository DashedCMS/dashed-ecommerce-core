<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Services\ProductReviews;

use Illuminate\Support\Facades\Mail;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Models\ProductQuestion;
use Dashed\DashedEcommerceCore\Mail\ProductQuestionAnsweredMail;
use Dashed\DashedEcommerceCore\Filament\Resources\ProductQuestionResource;

/**
 * Een vraag stellen (bezoeker) en beantwoorden (beheerder). Beantwoorden
 * mailt de vraagsteller het antwoord en zet de vraag, als de beheerder dat
 * kiest, op de productpagina.
 */
class ProductQuestionService
{
    public static function ask(Product $product, string $name, string $email, string $question, string $siteId): ProductQuestion
    {
        $record = ProductQuestion::create([
            'site_id' => $siteId,
            'product_id' => $product->getKey(),
            'product_group_id' => $product->product_group_id,
            'name' => ProductReviewSubmitter::clean($name, 80) ?? '',
            'email' => mb_substr(trim($email), 0, 191),
            'question' => ProductReviewSubmitter::clean($question, 2000) ?? '',
            'locale' => app()->getLocale(),
            'status' => ProductQuestion::STATUS_PENDING,
        ]);

        $productName = is_array($product->name) ? (string) reset($product->name) : (string) $product->name;

        AdminNotifier::notify(
            __('Nieuwe productvraag'),
            __(':naam stelde een vraag over :product: ":vraag"', [
                'naam' => $record->name,
                'product' => $productName,
                'vraag' => mb_strimwidth($record->question, 0, 200, '...'),
            ]),
            rescue(fn () => ProductQuestionResource::getUrl('edit', ['record' => $record]), null, false),
        );

        return $record;
    }

    public static function answer(ProductQuestion $question, string $answer, bool $show, bool $notifyCustomer, ?int $userId = null): void
    {
        $question->forceFill([
            'answer' => trim($answer),
            'answered_at' => now(),
            'answered_by' => $userId,
            'status' => $show ? ProductQuestion::STATUS_APPROVED : ($question->status === ProductQuestion::STATUS_APPROVED ? ProductQuestion::STATUS_PENDING : $question->status),
        ])->save();

        if ($notifyCustomer && filled($question->email)) {
            try {
                Mail::to($question->email)
                    ->locale($question->locale ?: app()->getLocale())
                    ->send(new ProductQuestionAnsweredMail($question));
                $question->forceFill(['customer_notified_at' => now()])->save();
            } catch (\Throwable $e) {
                report($e);
            }
        }
    }
}
