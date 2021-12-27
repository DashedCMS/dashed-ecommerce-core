<?php

namespace Qubiqx\QcommerceEcommerceCore\Filament\Resources\ProductCategoryResource\Pages;

use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Pages\EditRecord\Concerns\Translatable;
use Qubiqx\QcommerceEcommerceCore\Filament\Resources\ProductCategoryResource;
use Qubiqx\QcommerceEcommerceCore\Filament\Resources\ProductCharacteristicResource;

class EditProductCategory extends EditRecord
{
    use Translatable;

    protected static string $resource = ProductCategoryResource::class;
}
