<?php

namespace Dashed\DashedEcommerceCore\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;

class PricePerProductForUserImport implements ToCollection
{
    public function collection(Collection $rows): array
    {
        return $rows;
    }
}
