<?php

namespace Dashed\DashedEcommerceCore\Imports;

use App\Models\User;
use Maatwebsite\Excel\Concerns\ToArray;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Models\ProductGroup;
use Dashed\DashedEcommerceCore\Jobs\UpdateProductInformationJob;

class EANCodesToImport implements ToArray
{

    public function array(array $rows): void
    {
        unset($rows[0]);

        foreach ($rows as $row) {
            $EANCode = $row[0];
            if(!Product::where('ean', $EANCode)->exists()) {
                $product = Product::whereNull('ean')->first();
                if ($product) {
                    $product->ean = $EANCode;
                    $product->save();
                }
            }
        }
    }
}
