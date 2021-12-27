<?php

namespace Qubiqx\QcommerceEcommerceCore\Filament\Resources\ProductCharacteristicResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\CreateRecord\Concerns\Translatable;
use Qubiqx\QcommerceEcommerceCore\Filament\Resources\DiscountCodeResource;
use Qubiqx\QcommerceEcommerceCore\Filament\Resources\ProductCharacteristicResource;
use Qubiqx\QcommerceEcommerceCore\Models\ProductCharacteristic;

class CreateProductCharacteristic extends CreateRecord
{
    use Translatable;

    protected static string $resource = ProductCharacteristicResource::class;
}
