<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Services\ProductReviews;

use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Models\ProductReview;

/**
 * Aantal, gemiddelde en verdeling van de goedgekeurde reviews van een
 * product (of zijn groep), in één gegroepeerde query. Per verzoek onthouden
 * op de request-attributen: de productpagina, het schema en het blok vragen
 * er allemaal om.
 */
class ProductReviewStats
{
    protected const MEMO_ATTRIBUTE = 'dashed-ecommerce-core.product-review-stats';

    /**
     * @return array{count: int, average: float|null, distribution: array<int, int>}
     */
    public static function for(Product $product): array
    {
        $key = $product->product_group_id ? 'g' . $product->product_group_id : 'p' . $product->getKey();
        $memo = request()->attributes->get(self::MEMO_ATTRIBUTE, []);

        if (isset($memo[$key])) {
            return $memo[$key];
        }

        $rows = ProductReview::query()
            ->approved()
            ->forProduct($product)
            ->selectRaw('rating, count(*) as aantal')
            ->groupBy('rating')
            ->pluck('aantal', 'rating');

        $distribution = [];
        $count = 0;
        $sum = 0;
        foreach ([5, 4, 3, 2, 1] as $stars) {
            $amount = (int) ($rows[$stars] ?? 0);
            $distribution[$stars] = $amount;
            $count += $amount;
            $sum += $amount * $stars;
        }

        $memo[$key] = [
            'count' => $count,
            'average' => $count > 0 ? round($sum / $count, 1) : null,
            'distribution' => $distribution,
        ];
        request()->attributes->set(self::MEMO_ATTRIBUTE, $memo);

        return $memo[$key];
    }

    public static function flush(): void
    {
        request()->attributes->remove(self::MEMO_ATTRIBUTE);
    }
}
