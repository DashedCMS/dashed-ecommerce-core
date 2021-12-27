<?php

namespace Qubiqx\QcommerceEcommerceCore\Filament\Resources\ProductCharacteristicResource\Pages;

use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Pages\ListRecords\Concerns\Translatable;
use Qubiqx\QcommerceEcommerceCore\Filament\Resources\DiscountCodeResource;
use Qubiqx\QcommerceEcommerceCore\Filament\Resources\ProductCharacteristicResource;

class ListProductCharacteristic extends ListRecords
{
    use Translatable;

    protected static string $resource = ProductCharacteristicResource::class;
}
