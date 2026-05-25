<?php

namespace Dashed\DashedEcommerceCore\Services\Gs1;

use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\ValueObjects\Gs1Row;
use Dashed\DashedEcommerceCore\Models\ProductCategory;

/**
 * Lost de 13 GS1-kolomwaarden op voor een product door drie lagen
 * te stapelen: shop-default (Customsetting) -> categorie-override
 * (eerste gekoppelde categorie met een waarde) -> product-override.
 * Verbruikers vragen `resolve($product)` en krijgen een ingevulde
 * Gs1Row terug met (initieel) een lege GTIN.
 */
class Gs1MetaResolver
{
    public function __construct(private readonly int $siteId)
    {
    }

    public function resolve(Product $product, ?string $gtin = null): Gs1Row
    {
        return new Gs1Row(
            gtin: $gtin,
            status: 'Actief',
            classification: $this->pickString($product, 'gs1_classification', 'gs1_default_classification'),
            consumerUnit: $this->pickConsumerUnit($product),
            packagingType: $this->pickString($product, 'gs1_packaging_type', 'gs1_default_packaging_type'),
            country: $this->pickString($product, 'gs1_country', 'gs1_default_country', 'Nederland'),
            description: $this->buildDescription($product),
            language: $this->pickString($product, 'gs1_language', 'gs1_default_language', 'Nederlands'),
            brand: $this->pickString($product, 'gs1_brand', 'gs1_default_brand'),
            subBrand: $this->pickString($product, 'gs1_sub_brand', 'gs1_default_sub_brand'),
            quantity: $this->pickInt($product, 'gs1_quantity', 'gs1_default_quantity', 1),
            unit: $this->pickString($product, 'gs1_unit', 'gs1_default_unit', 'Stuks'),
            imageUrl: $this->resolveImageUrl($product),
        );
    }

    private function resolveImageUrl(Product $product): ?string
    {
        if ($product->gs1_image_url) {
            return (string) $product->gs1_image_url;
        }

        $firstImage = $product->firstImage ?? null;
        if (! $firstImage) {
            return null;
        }

        try {
            $media = mediaHelper()->getSingleMedia($firstImage, 'original');
            $url = $media->url ?? null;
            if (! $url) {
                return null;
            }

            return mb_strlen($url) > 500 ? null : $url;
        } catch (\Throwable) {
            return null;
        }
    }

    private function pickString(
        Product $product,
        string $productColumn,
        string $defaultSetting,
        ?string $hardcodedFallback = null,
    ): ?string {
        $value = $this->fromProductOrCategories($product, $productColumn);
        if ($value !== null && $value !== '') {
            return (string) $value;
        }

        $shopDefault = Customsetting::get($defaultSetting, $this->siteId);
        if ($shopDefault !== null && $shopDefault !== '') {
            return (string) $shopDefault;
        }

        return $hardcodedFallback;
    }

    private function pickInt(
        Product $product,
        string $productColumn,
        string $defaultSetting,
        ?int $hardcodedFallback = null,
    ): ?int {
        $value = $this->fromProductOrCategories($product, $productColumn);
        if ($value !== null && $value !== '') {
            return (int) $value;
        }

        $shopDefault = Customsetting::get($defaultSetting, $this->siteId);
        if ($shopDefault !== null && $shopDefault !== '') {
            return (int) $shopDefault;
        }

        return $hardcodedFallback;
    }

    private function pickConsumerUnit(Product $product): string
    {
        $productValue = $product->gs1_consumer_unit;
        if ($productValue !== null) {
            return $productValue ? 'Ja' : 'Nee';
        }

        foreach ($this->categoriesFor($product) as $category) {
            if ($category->gs1_consumer_unit !== null) {
                return $category->gs1_consumer_unit ? 'Ja' : 'Nee';
            }
        }

        $shopDefault = Customsetting::get('gs1_default_consumer_unit', $this->siteId, 1);

        return ((bool) $shopDefault) ? 'Ja' : 'Nee';
    }

    private function fromProductOrCategories(Product $product, string $column): mixed
    {
        $productValue = $product->{$column} ?? null;
        if ($productValue !== null && $productValue !== '') {
            return $productValue;
        }

        foreach ($this->categoriesFor($product) as $category) {
            $value = $category->{$column} ?? null;
            if ($value !== null && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    /**
     * @return iterable<ProductCategory>
     */
    private function categoriesFor(Product $product): iterable
    {
        if (! method_exists($product, 'productCategories')) {
            return [];
        }

        return $product->productCategories;
    }

    private function buildDescription(Product $product): ?string
    {
        $name = $product->name ?? null;
        if (! $name) {
            return null;
        }

        $name = (string) $name;
        if (mb_strlen($name) <= 300) {
            return $name;
        }

        return mb_substr($name, 0, 300);
    }
}
