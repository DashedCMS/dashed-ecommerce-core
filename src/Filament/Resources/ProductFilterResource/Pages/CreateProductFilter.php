<?php

namespace Qubiqx\QcommerceEcommerceCore\Filament\Resources\ProductFilterResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\CreateRecord\Concerns\Translatable;
use Qubiqx\QcommerceEcommerceCore\Filament\Resources\ProductFilterResource;

class CreateProductFilter extends CreateRecord
{
    use Translatable;

    protected static string $resource = ProductFilterResource::class;
}
