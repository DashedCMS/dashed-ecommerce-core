<?php

namespace Dashed\DashedEcommerceCore\Classes;

use Dashed\DashedEcommerceCore\Models\Product;

/**
 * De filters en attributen van een product zoals de productfeed ze opbouwt.
 * Staat los van ProductFeedResource zodat het Bol-titelsjabloon, het voorbeeld
 * in het CMS en de feed precies dezelfde waarden gebruiken.
 */
class ProductFeedAttributes
{
    /**
     * Zet op elke filter uit simpleFilters() welke optie dit product heeft.
     * Een filter met maar één optie telt als gekozen.
     */
    public static function activeFilters(Product $product, array $simpleFilters): array
    {
        $productFilters = $product->relationLoaded('productFilters') ? $product->productFilters : collect();

        $activeByFilterId = [];
        foreach ($productFilters as $pf) {
            $activeByFilterId[(int) $pf->product_filter_id] = (int) ($pf->pivot->product_filter_option_id ?? 0);
        }

        foreach ($simpleFilters as &$filter) {
            $filterId = (int) ($filter['id'] ?? 0);
            if (! $filterId) {
                continue;
            }

            $active = $activeByFilterId[$filterId] ?? null;

            if ($active) {
                $filter['active'] = $active;
            } elseif (count($filter['options'] ?? []) === 1) {
                $filter['active'] = $filter['options'][0]['id'];
            } else {
                $filter['active'] = null;
            }
        }
        unset($filter);

        return $simpleFilters;
    }

    /**
     * Naam => waarde: eerst de actieve filters van de groep, dan de kenmerken
     * van de groep, dan die van het product (een latere wint).
     *
     * @return array<string, mixed>
     */
    public static function forProduct(Product $product, array $filters): array
    {
        $attributes = [];

        if ($product->productGroup && $product->productGroup->relationLoaded('activeProductFilters')) {
            foreach ($product->productGroup->activeProductFilters as $filterModel) {
                $filterId = (int) $filterModel->id;
                $activeId = null;

                foreach ($filters as $f) {
                    if ((int) ($f['id'] ?? 0) === $filterId) {
                        $activeId = $f['active'] ?? null;

                        break;
                    }
                }

                $value = '';
                if ($activeId) {
                    $opt = $filterModel->productFilterOptions->firstWhere('id', (int) $activeId);
                    $value = $opt?->name ?? '';
                }

                if ($value !== '') {
                    $attributes[$filterModel->name] = $value;
                }
            }
        }

        if ($product->productGroup) {
            foreach ($product->productGroup->allCharacteristicsWithoutFilters() as $gc) {
                if (! empty($gc['value'])) {
                    $attributes[$gc['name']] = $gc['value'];
                }
            }
        }

        foreach ($product->allCharacteristics() as $gc) {
            if (! empty($gc['value'])) {
                $attributes[$gc['name']] = $gc['value'];
            }
        }

        return $attributes;
    }
}
