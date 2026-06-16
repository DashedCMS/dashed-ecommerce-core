<?php

namespace Dashed\DashedEcommerceCore\Services\Gs1;

use Dashed\DashedEcommerceCore\Models\Product;

/**
 * Bouwt een GS1-formaat Excel met alle producten die nog geen EAN
 * hebben. Elke rij wordt opgelost via de hybrid resolver
 * (shop-default → categorie-override → product-override) en krijgt
 * een placeholder GTIN (1, 2, 3 ...) zodat GS1 ze na upload
 * vervangt door echte GTINs.
 */
class Gs1ExportBuilder
{
    public function __construct(private readonly Gs1FileWriter $writer)
    {
    }

    public function buildForProductsWithoutEan(int $siteId, string $outputPath): int
    {
        $products = Product::query()
            ->withoutEan()
            ->where('public', true)
            ->where('is_bundle', false)
            ->with('productCategories')
            ->orderBy('id')
            ->get();

        $resolver = new Gs1MetaResolver($siteId);

        $rows = [];
        foreach ($products as $product) {
            $rows[] = $resolver->resolve($product);
        }

        $this->writer->write($rows, $outputPath);

        return count($rows);
    }
}
