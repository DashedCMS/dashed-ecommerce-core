<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources\ProductCharacteristicResource\Pages;

use Filament\Actions\LocaleSwitcher;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\CreateRecord\Concerns\Translatable;
use Dashed\DashedEcommerceCore\Filament\Resources\ProductCharacteristicResource;

class CreateProductCharacteristic extends CreateRecord
{
    use Translatable;

    protected static string $resource = ProductCharacteristicResource::class;

    protected function getActions(): array
    {
        return [
          LocaleSwitcher::make(),
        ];
    }
}
