<?php

namespace Dashed\DashedEcommerceCore\Imports;

use App\Models\User;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Dashed\DashedEcommerceCore\Models\Product;
use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Concerns\ToCollection;

class PricePerProductForUserImport implements ToCollection
{
    public function collection(Collection $rows): array
    {
        return $rows;
    }
}
