<?php

namespace Qubiqx\QcommerceEcommerceCore\Filament\Resources\ProductFilterOptionResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\CreateRecord\Concerns\Translatable;
use Qubiqx\QcommerceEcommerceCore\Filament\Resources\ProductFilterOptionResource;

class CreateProductFilterOption extends CreateRecord
{
    use Translatable;

    protected static string $resource = ProductFilterOptionResource::class;
}
