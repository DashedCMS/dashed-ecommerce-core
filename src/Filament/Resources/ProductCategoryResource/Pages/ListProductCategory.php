<?php

namespace Qubiqx\QcommerceEcommerceCore\Filament\Resources\ProductCategoryResource\Pages;

use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Pages\ListRecords\Concerns\Translatable;
use Qubiqx\QcommerceEcommerceCore\Filament\Resources\DiscountCodeResource;
use Qubiqx\QcommerceEcommerceCore\Filament\Resources\ProductCategoryResource;
use Qubiqx\QcommerceEcommerceCore\Filament\Resources\ProductCharacteristicResource;

class ListProductCategory extends ListRecords
{
    use Translatable;

    protected static string $resource = ProductCategoryResource::class;
}
