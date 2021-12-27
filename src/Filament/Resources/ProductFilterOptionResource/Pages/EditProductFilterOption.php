<?php

namespace Qubiqx\QcommerceEcommerceCore\Filament\Resources\ProductFilterOptionResource\Pages;

use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Pages\EditRecord\Concerns\Translatable;
use Qubiqx\QcommerceEcommerceCore\Filament\Resources\ProductFilterOptionResource;

class EditProductFilterOption extends EditRecord
{
    use Translatable;

    protected static string $resource = ProductFilterOptionResource::class;
}
