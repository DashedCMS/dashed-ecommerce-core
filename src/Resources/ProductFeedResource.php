<?php

namespace Dashed\DashedEcommerceCore\Resources;

use Dashed\DashedEcommerceCore\Models\Product;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \Dashed\DashedEcommerceCore\Models\Product
 */
class ProductFeedResource extends JsonResource
{
    public function toArray($request): array
    {
        /** @var Product $product */
        $product = $this->resource;

        $categories = $product->productCategories?->pluck('name')->values()->all() ?? [];

        $filters = $product->productGroup?->simpleFilters() ?? [];
        $productFilters = $product->relationLoaded('productFilters') ? $product->productFilters : collect();

        // Map: filterId => activeOptionId
        $activeByFilterId = [];
        foreach ($productFilters as $pf) {
            $activeByFilterId[(int) $pf->product_filter_id] = (int) ($pf->pivot->product_filter_option_id ?? 0);
        }

        // Zet active op filters
        foreach ($filters as &$filter) {
            $filterId = (int) ($filter['id'] ?? 0);
            if (! $filterId) continue;

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

        $images = $product->originalImagesToShow;
        if (empty($images) && $product->productGroup) {
            $images = $product->productGroup->originalImagesToShow ?? [];
        }
        $imageLink = $images[0] ?? null;

        $stock = $product->total_stock;
        $availability = $product->in_stock;

        if ($stock === null || $availability === null) {
            $stock = $product->directSellableStock();
            $availability = $stock > 0;
        }

        $description = null;
        $shortDescription = null;

        if (str(cms()->convertToHtml($product->description))->stripTags()->toString()) {
            $description = $product->replaceContentVariables($product->description, $filters);
        } elseif ($product->productGroup?->description) {
            $description = $product->productGroup->replaceContentVariables(
                $product->productGroup->description,
                $filters,
                $product
            );
        }

        if ($product->short_description) {
            $shortDescription = $product->replaceContentVariables($product->short_description, $filters);
        } elseif ($product->productGroup?->short_description) {
            $shortDescription = $product->productGroup->replaceContentVariables(
                $product->productGroup->short_description,
                $filters,
                $product
            );
        }

        // Attributes (veiligere keys)
        $attributes = [];

        if ($product->productGroup && $product->productGroup->relationLoaded('activeProductFilters')) {
            foreach ($product->productGroup->activeProductFilters as $filterModel) {
                $filterId = (int) $filterModel->id;
                $activeId = null;

                foreach ($filters as $f) {
                    if ((int)($f['id'] ?? 0) === $filterId) {
                        $activeId = $f['active'] ?? null;
                        break;
                    }
                }

                $value = '';
                if ($activeId) {
                    $opt = $filterModel->productFilterOptions->firstWhere('id', (int)$activeId);
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

        $array = [
            'id' => $product->id,
            'product_group_id' => $product->product_group_id ?? ($product->productGroup->id ?? null),
            'product_group_title' => $product->product_group_id ?? ($product->productGroup->name ?? null),
            'title' => $product->name,
            'link' => url($product->getUrl()),
            'price' => $product->currentPrice,
            'sale_price' => $product->discountPrice,
            'availability' => (bool) $availability,
            'stock' => $stock,
            'description' => $description,
            'short_description' => $shortDescription,
            'ean' => $product->ean,
            'sku' => $product->sku,
            'image_link' => $imageLink,
            'images' => $images,
            'first_category' => $categories[0] ?? null,
            'categories' => $categories,
            'width' => $product->width,
            'height' => $product->height,
            'length' => $product->length,
            'weight' => $product->weight,

            // nieuw:
            'attributes' => $attributes,
        ];

        $array = array_merge($array, $attributes);

        if (! empty($images)) {
            foreach (array_values(array_slice($images, 1)) as $idx => $url) {
                $array['image_link_' . ($idx + 2)] = $url;
            }
        }

        return $array;
    }
}
