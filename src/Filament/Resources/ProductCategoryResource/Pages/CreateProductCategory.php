<?php

namespace Qubiqx\QcommerceEcommerceCore\Filament\Resources\ProductCategoryResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\CreateRecord\Concerns\Translatable;
use Qubiqx\QcommerceEcommerceCore\Filament\Resources\DiscountCodeResource;
use Qubiqx\QcommerceEcommerceCore\Filament\Resources\ProductCategoryResource;
use Qubiqx\QcommerceEcommerceCore\Filament\Resources\ProductCharacteristicResource;
use Qubiqx\QcommerceEcommerceCore\Models\ProductCharacteristic;

class CreateProductCategory extends CreateRecord
{
    use Translatable;

    protected static string $resource = ProductCategoryResource::class;
}
