<?php

namespace Qubiqx\QcommerceEcommerceCore\Filament\Resources\ProductFilterOptionResource\Pages;

use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Pages\ListRecords\Concerns\Translatable;
use Qubiqx\QcommerceEcommerceCore\Filament\Resources\ProductCategoryResource;
use Qubiqx\QcommerceEcommerceCore\Filament\Resources\ProductFilterOptionResource;
use Qubiqx\QcommerceEcommerceCore\Filament\Resources\ProductFilterResource;

class ListProductFilterOption extends ListRecords
{
    use Translatable;

    protected static string $resource = ProductFilterOptionResource::class;
}
