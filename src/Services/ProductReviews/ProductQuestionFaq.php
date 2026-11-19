<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Services\ProductReviews;

use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Models\ProductQuestion;

/**
 * Beantwoorde, goedgekeurde productvragen in het FAQPage-schema van de
 * productpagina. Google wil één FAQPage per pagina, dus:
 * - heeft het product al FAQ's (Product::buildFaqJsonLd()), dan komen de
 *   vragen in diezelfde FAQPage;
 * - staat er een FAQ-blok in de inhoud (FaqSchema in dashed-core, dat de
 *   head zelf als FAQPage print), dan komen de vragen er niet bij;
 * - anders wordt het een eigen FAQPage.
 */
class ProductQuestionFaq
{
    public const MAX_QUESTIONS = 20;

    public static function merge(Product $product, ?string $faqJson): ?string
    {
        if (! rescue(fn () => ProductReviewSettings::questionsEnabled(), false, false)) {
            return $faqJson;
        }

        if (class_exists(\Dashed\DashedCore\Classes\Seo\FaqSchema::class)
            && rescue(fn () => \Dashed\DashedCore\Classes\Seo\FaqSchema::for($product), null, false)) {
            return $faqJson;
        }

        $entities = ProductQuestion::query()
            ->visible()
            ->forProduct($product)
            ->latest('answered_at')
            ->limit(self::MAX_QUESTIONS)
            ->get()
            ->map(fn (ProductQuestion $question) => [
                '@type' => 'Question',
                'name' => trim($question->question),
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => trim((string) $question->answer)],
            ])
            ->filter(fn (array $entity) => $entity['name'] !== '' && $entity['acceptedAnswer']['text'] !== '')
            ->values()
            ->all();

        if ($entities === []) {
            return $faqJson;
        }

        $faq = $faqJson ? json_decode($faqJson, true) : null;
        if (! is_array($faq) || ($faq['@type'] ?? null) !== 'FAQPage') {
            $faq = ['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => []];
        }

        $known = array_map(fn ($entity) => mb_strtolower((string) ($entity['name'] ?? '')), (array) $faq['mainEntity']);
        foreach ($entities as $entity) {
            if (! in_array(mb_strtolower($entity['name']), $known, true)) {
                $faq['mainEntity'][] = $entity;
            }
        }

        return json_encode($faq, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
