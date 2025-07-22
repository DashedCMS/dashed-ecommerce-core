<?php

namespace Dashed\DashedEcommerceCore\Imports;

use Maatwebsite\Excel\Concerns\ToArray;
use Dashed\DashedEcommerceCore\Models\Product;

class EANCodesToImport implements ToArray
{
    public function array(array $rows): void
    {
        foreach ($rows as $row) {
            $EANCode = $row[0];
            if (! Product::where('ean', $EANCode)->exists()) {
                $product = Product::whereNull('ean')->first();
                if ($product) {
                    $product->ean = $EANCode;
                    $product->saveQuietly();
                }
            }
        }
    }
}
