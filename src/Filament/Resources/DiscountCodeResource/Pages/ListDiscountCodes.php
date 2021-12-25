<?php

namespace Qubiqx\QcommerceEcommerceCore\Filament\Resources\DiscountCodeResource\Pages;

use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Pages\ListRecords\Concerns\Translatable;
use Qubiqx\QcommerceEcommerceCore\Filament\Resources\DiscountCodeResource;

class ListDiscountCodes extends ListRecords
{
    use Translatable;

    protected static string $resource = DiscountCodeResource::class;
}
