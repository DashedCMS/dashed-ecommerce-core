<?php

namespace Dashed\DashedEcommerceCore\Imports;

use Maatwebsite\Excel\Concerns\ToArray;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Models\ProductGroup;
use Dashed\DashedEcommerceCore\Jobs\UpdateProductInformationJob;

class ProductsToEditImport implements ToArray
{
    public function array(array $rows): void
    {
        if (! count($rows)) {
            return;
        }

        $headerRow = array_shift($rows);

        $headerMap = [];
        foreach ($headerRow as $index => $header) {
            $headerMap[$header] = $index;
        }

        $productGroupIds = [];

        $priceFields = ecommerce()->builder('productPriceFields');

        foreach ($rows as $row) {
            $productId = $this->getValue($row, $headerMap, 'Product ID (niet wijzigen)');

            if (! $productId) {
                continue;
            }

            $product = Product::find($productId);

            if (! $product) {
                continue;
            }

            // Dynamische prijsvelden
            foreach ($priceFields as $key => $priceField) {
                $label = $priceField['label'] ?? $key;

                if (array_key_exists($label, $headerMap)) {
                    $product->{$key} = $this->getValue($row, $headerMap, $label);
                }
            }

            // Vaste velden
            $product->stock = $this->getValue($row, $headerMap, 'Voorraad', 0);
            $product->ean = $this->getValue($row, $headerMap, 'EAN');
            $product->sku = $this->getValue($row, $headerMap, 'SKU');
            $product->vat_rate = $this->getValue($row, $headerMap, 'BTW percentage');
            $product->weight = $this->getValue($row, $headerMap, 'Gewicht (in KG)');
            $product->length = $this->getValue($row, $headerMap, 'Lengte (in CM)');
            $product->width = $this->getValue($row, $headerMap, 'Breedte (in CM)');
            $product->height = $this->getValue($row, $headerMap, 'Hoogte (in CM)');

            if ($product->isDirty()) {
                $product->save();

                if ($product->product_group_id) {
                    $productGroupIds[] = $product->product_group_id;
                }
            }
        }

        foreach (ProductGroup::whereIn('id', array_unique($productGroupIds))->get() as $productGroup) {
            UpdateProductInformationJob::dispatch($productGroup, false)
                ->onQueue('ecommerce');
        }
    }

    protected function getValue(array $row, array $headerMap, string $header, mixed $default = ''): mixed
    {
        $index = $headerMap[$header] ?? null;

        if ($index === null) {
            return $default;
        }

        return $row[$index] ?? $default;
    }
}
