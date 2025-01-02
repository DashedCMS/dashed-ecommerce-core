<?php

namespace Dashed\DashedEcommerceCore\Imports;

use App\Models\User;
use Dashed\DashedEcommerceCore\Models\Product;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToArray;

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

        foreach ($rows as $row) {
            $product = Product::find($row[0]);
            if ($product) {
                $product->price = $row[2];
                $product->new_price = $row[3];
                $product->save();
            }
        }
    }
}
