<?php

namespace Qubiqx\QcommerceEcommerceCore\Filament\Resources\ProductFilterResource\Pages;

use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Pages\EditRecord\Concerns\Translatable;
use Qubiqx\QcommerceEcommerceCore\Filament\Resources\ProductFilterResource;

class EditProductFilter extends EditRecord
{
    use Translatable;

    protected static string $resource = ProductFilterResource::class;
}
