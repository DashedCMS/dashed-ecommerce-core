<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Classes\Seo;

use Spatie\SchemaOrg\Schema;
use Spatie\SchemaOrg\BaseType;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Models\ProductReview;
use Dashed\DashedEcommerceCore\Services\ProductReviews\ProductReviewStats;
use Dashed\DashedEcommerceCore\Services\ProductReviews\ProductReviewSettings;

/**
 * aggregateRating en review op het productschema, uitsluitend uit de eigen
 * goedgekeurde productreviews. Nooit de winkelscore (Kiyoh, WebwinkelKeur,
 * Google): Google straft een winkelscore als productscore af. Zonder
 * goedgekeurde review, of met productreviews uit voor de site, komt er
 * niets op het schema.
 */
class ProductReviewSchema
{
    public const MAX_REVIEWS = 5;

    public static function apply(BaseType $node, Product $product): BaseType
    {
        if (! rescue(fn () => ProductReviewSettings::reviewsEnabled(), false, false)) {
            return $node;
        }

        $stats = rescue(fn () => ProductReviewStats::for($product), null, false);

        if (! $stats || $stats['count'] < 1) {
            return $node;
        }

        $node->setProperty('aggregateRating', Schema::aggregateRating()
            ->ratingValue($stats['average'])
            ->reviewCount($stats['count'])
            ->bestRating(5)
            ->worstRating(1));

        $reviews = ProductReview::query()
            ->approved()
            ->forProduct($product)
            ->latest('approved_at')
            ->latest('id')
            ->limit(self::MAX_REVIEWS)
            ->get();

        $node->setProperty('review', $reviews->map(function (ProductReview $review) {
            $schema = Schema::review()
                ->author(Schema::person()->name($review->name))
                ->datePublished(($review->approved_at ?? $review->created_at)?->toDateString())
                ->reviewRating(Schema::rating()->ratingValue($review->rating)->bestRating(5)->worstRating(1));

            if (filled($review->title)) {
                $schema->name($review->title);
            }

            if (filled($review->body)) {
                $schema->reviewBody($review->body);
            }

            return $schema;
        })->all());

        return $node;
    }
}
