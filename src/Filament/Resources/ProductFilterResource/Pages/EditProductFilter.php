<?php

namespace Qubiqx\QcommerceEcommerceCore\Filament\Resources\ProductFilterResource\Pages;

use Illuminate\Support\Str;
use Qubiqx\QcommerceCore\Classes\Sites;
use Filament\Resources\Pages\EditRecord;
use Qubiqx\QcommerceEcommerceCore\Filament\Resources\ProductFilterResource;
use Qubiqx\QcommerceEcommerceCore\Models\ProductCategory;
use Filament\Resources\Pages\EditRecord\Concerns\Translatable;
use Qubiqx\QcommerceEcommerceCore\Filament\Resources\ProductCategoryResource;

class EditProductFilter extends EditRecord
{
    use Translatable;

    protected static string $resource = ProductFilterResource::class;
}
