<?php

namespace Qubiqx\QcommerceEcommerceCore\Filament\Resources\ProductFilterResource\Pages;

use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Pages\ListRecords\Concerns\Translatable;
use Qubiqx\QcommerceEcommerceCore\Filament\Resources\ProductCategoryResource;
use Qubiqx\QcommerceEcommerceCore\Filament\Resources\ProductFilterResource;

class ListProductFilter extends ListRecords
{
    use Translatable;

    protected static string $resource = ProductFilterResource::class;
}
