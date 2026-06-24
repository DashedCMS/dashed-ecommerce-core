<?php

// packages/dashed/dashed-ecommerce-core/src/ContentQuality/ProductWithoutImageCheck.php

namespace Dashed\DashedEcommerceCore\ContentQuality;

use Illuminate\Support\Collection;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedCore\ContentQuality\QualityIssue;
use Dashed\DashedEcommerceCore\Filament\Resources\ProductResource;
use Dashed\DashedCore\ContentQuality\Contracts\ContentQualityCheck;

class ProductWithoutImageCheck implements ContentQualityCheck
{
    public function key(): string
    {
        return 'product_without_image';
    }

    public function label(): string
    {
        return 'Product zonder afbeelding';
    }

    public function count(string $siteId): int
    {
        return $this->items($siteId)->count();
    }

    public function items(string $siteId): Collection
    {
        return Product::query()
            ->get()
            ->filter(fn (Product $product) => empty($product->images))
            ->filter(fn (Product $product) => empty($product->site_ids) || in_array($siteId, $product->site_ids, true))
            ->map(fn (Product $product) => new QualityIssue(
                checkKey: 'product_without_image',
                title: $product->name,
                subtitle: 'geen afbeelding',
                editUrl: ProductResource::getUrl('edit', ['record' => $product]),
                modelClass: Product::class,
                modelId: $product->id,
            ))
            ->values();
    }

    public function resolutions(): array
    {
        return ['link'];
    }
}
