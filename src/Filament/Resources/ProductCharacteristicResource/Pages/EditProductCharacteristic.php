<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources\ProductCharacteristicResource\Pages;

use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Pages\EditRecord\Concerns\Translatable;
use Dashed\DashedEcommerceCore\Filament\Resources\ProductCharacteristicResource;

class EditProductCharacteristic extends EditRecord
{
    use Translatable;

    protected static string $resource = ProductCharacteristicResource::class;
}
