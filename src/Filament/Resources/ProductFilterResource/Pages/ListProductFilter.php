<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources\ProductFilterResource\Pages;

use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Pages\ListRecords\Concerns\Translatable;
use Dashed\DashedEcommerceCore\Filament\Resources\ProductFilterResource;

class ListProductFilter extends ListRecords
{
    use Translatable;

    protected static string $resource = ProductFilterResource::class;
}
