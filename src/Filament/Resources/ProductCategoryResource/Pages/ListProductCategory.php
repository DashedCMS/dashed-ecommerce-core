<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources\ProductCategoryResource\Pages;

use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Pages\ListRecords\Concerns\Translatable;
use Dashed\DashedEcommerceCore\Filament\Resources\ProductCategoryResource;

class ListProductCategory extends ListRecords
{
    use Translatable;

    protected static string $resource = ProductCategoryResource::class;
}
