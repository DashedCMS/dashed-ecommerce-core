<?php

namespace Qubiqx\QcommerceEcommerceCore\Filament\Resources\ProductFilterOptionResource\Pages;

use Illuminate\Support\Str;
use Qubiqx\QcommerceCore\Classes\Sites;
use Filament\Resources\Pages\CreateRecord;
use Qubiqx\QcommerceEcommerceCore\Filament\Resources\ProductFilterOptionResource;
use Qubiqx\QcommerceEcommerceCore\Filament\Resources\ProductFilterResource;
use Qubiqx\QcommerceEcommerceCore\Models\ProductCategory;
use Filament\Resources\Pages\CreateRecord\Concerns\Translatable;
use Qubiqx\QcommerceEcommerceCore\Filament\Resources\ProductCategoryResource;

class CreateProductFilterOption extends CreateRecord
{
    use Translatable;

    protected static string $resource = ProductFilterOptionResource::class;
}
