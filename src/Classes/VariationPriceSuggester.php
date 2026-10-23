<?php

namespace Dashed\DashedEcommerceCore\Classes;

use Illuminate\Support\Facades\DB;
use Dashed\DashedEcommerceCore\Models\ProductGroup;

/**
 * Stelt voor een nieuwe variatie een prijs voor op basis van bestaande
 * producten in dezelfde groep die op de gekozen filters (bv. Maat) dezelfde
 * optie hebben. Bij verschillende prijzen wint de meest voorkomende
 * combinatie van prijs en van-prijs, bij gelijkspel de hoogste prijs.
 */
class VariationPriceSuggester
{
    /**
     * @param  array<int, array<int, int>>  $variations  elke variatie als lijst van product_filter_option_id's
     * @param  array<int, int>  $filterIds
     * @return array<string, array{price: float, new_price: float|null, matches: int}|null> sleutel = implode('-', optie-id's)
     */
    public static function forVariations(ProductGroup $group, array $variations, array $filterIds): array
    {
        $filterIds = array_values(array_unique(array_map('intval', array_filter($filterIds))));
        $result = [];

        foreach ($variations as $variation) {
            $result[static::key($variation)] = null;
        }

        if (! count($filterIds) || ! count($variations)) {
            return $result;
        }

        $products = DB::table('dashed__products')
            ->where('product_group_id', $group->id)
            ->whereNull('deleted_at')
            ->whereNotNull('price')
            ->get(['id', 'price', 'new_price'])
            ->keyBy('id');

        if ($products->isEmpty()) {
            return $result;
        }

        $optionFilter = DB::table('dashed__product_filter_options')
            ->whereIn('product_filter_id', $filterIds)
            ->pluck('product_filter_id', 'id');

        // product-id => [filter-id => optie-id], alleen voor de gekozen filters
        $productOptions = [];
        DB::table('dashed__product_filter')
            ->whereIn('product_id', $products->keys())
            ->whereIn('product_filter_id', $filterIds)
            ->get(['product_id', 'product_filter_id', 'product_filter_option_id'])
            ->each(function ($row) use (&$productOptions) {
                $productOptions[$row->product_id][$row->product_filter_id] = (int) $row->product_filter_option_id;
            });

        foreach ($variations as $variation) {
            $wanted = [];
            foreach ($variation as $optionId) {
                $filterId = $optionFilter[$optionId] ?? null;
                if ($filterId) {
                    $wanted[$filterId] = (int) $optionId;
                }
            }

            if (count($wanted) !== count($filterIds)) {
                continue;
            }

            $counts = [];
            foreach ($productOptions as $productId => $options) {
                foreach ($wanted as $filterId => $optionId) {
                    if (($options[$filterId] ?? null) !== $optionId) {
                        continue 2;
                    }
                }

                $product = $products[$productId];
                $price = round((float) $product->price, 2);
                $newPrice = $product->new_price !== null && (float) $product->new_price > 0
                    ? round((float) $product->new_price, 2)
                    : null;
                $pairKey = $price . '|' . $newPrice;
                $counts[$pairKey] ??= ['price' => $price, 'new_price' => $newPrice, 'count' => 0];
                $counts[$pairKey]['count']++;
            }

            if (! count($counts)) {
                continue;
            }

            usort($counts, fn ($a, $b) => [$b['count'], $b['price']] <=> [$a['count'], $a['price']]);

            $result[static::key($variation)] = [
                'price' => $counts[0]['price'],
                'new_price' => $counts[0]['new_price'],
                'matches' => array_sum(array_column($counts, 'count')),
            ];
        }

        return $result;
    }

    /**
     * @param  array<int, int>  $variation
     */
    public static function key(array $variation): string
    {
        return implode('-', array_values($variation));
    }
}
