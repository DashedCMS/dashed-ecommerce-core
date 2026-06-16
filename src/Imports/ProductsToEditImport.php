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

            // Dynamische prijsvelden — alleen zetten als de kolom bestaat én de cel
            // gevuld is. Een lege cel betekent: laat dit veld ongewijzigd (zo
            // wordt '' nooit naar een decimal-kolom geschreven en blijft bestaande
            // data behouden).
            foreach ($priceFields as $key => $priceField) {
                $label = $priceField['label'] ?? $key;
                $this->applyValue($product, $row, $headerMap, $label, $key);
            }

            // Vaste velden — zelfde regel: alleen bijwerken als de kolom aanwezig en gevuld is.
            $this->applyValue($product, $row, $headerMap, 'Voorraad', 'stock', fn ($value) => (int) $value);
            $this->applyValue($product, $row, $headerMap, 'EAN', 'ean');
            $this->applyValue($product, $row, $headerMap, 'SKU', 'sku');
            $this->applyValue($product, $row, $headerMap, 'BTW percentage', 'vat_rate');
            $this->applyValue($product, $row, $headerMap, 'Gewicht (in KG)', 'weight');
            $this->applyValue($product, $row, $headerMap, 'Lengte (in CM)', 'length');
            $this->applyValue($product, $row, $headerMap, 'Breedte (in CM)', 'width');
            $this->applyValue($product, $row, $headerMap, 'Hoogte (in CM)', 'height');

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

    /**
     * Set $attribute on the product only when the column exists in the file and
     * the cell is not empty. Empty cells leave the field unchanged — this avoids
     * writing '' into numeric/decimal columns ("Incorrect decimal value: ''") and
     * prevents wiping existing values when a cell is left blank.
     */
    protected function applyValue(Product $product, array $row, array $headerMap, string $header, string $attribute, ?callable $cast = null): void
    {
        if (! array_key_exists($header, $headerMap)) {
            return;
        }

        $value = $this->getValue($row, $headerMap, $header);

        if ($value === '' || $value === null) {
            return;
        }

        $product->{$attribute} = $cast ? $cast($value) : $value;
    }
}
