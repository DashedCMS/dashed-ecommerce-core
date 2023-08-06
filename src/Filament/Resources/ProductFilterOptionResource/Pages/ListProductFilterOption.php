<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources\ProductFilterOptionResource\Pages;

use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Pages\ListRecords\Concerns\Translatable;
use Dashed\DashedEcommerceCore\Filament\Resources\ProductFilterOptionResource;

class ListProductFilterOption extends ListRecords
{
    use Translatable;

    protected static string $resource = ProductFilterOptionResource::class;
}
