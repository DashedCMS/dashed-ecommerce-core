<?php

namespace Dashed\DashedEcommerceCore\Imports;

use App\Models\User;
use Maatwebsite\Excel\Concerns\ToArray;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Models\ProductGroup;
use Dashed\DashedEcommerceCore\Jobs\UpdateProductInformationJob;

class ProductsToEditImport implements ToArray
{
    public User $user;

    public function __construct(User $user)
    {
        $this->user = $user;
    }

    public function array(array $rows): void
    {
        unset($rows[0]);

        $productGroupIds = [];

        foreach ($rows as $row) {
            $product = Product::find($row[0]);
            if ($product) {
                $product->price = $row[2];
                $product->new_price = $row[3];
                $product->ean = $row[4];
                $product->vat_rate = $row[5];
                $product->save();

                $productGroupIds[] = $product->product_group_id;
            }
        }

        foreach (ProductGroup::whereIn('id', $productGroupIds)->get() as $productGroup) {
            UpdateProductInformationJob::dispatch($productGroup, false)->onQueue('ecommerce');
        }
    }
}
