<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources\ProductFilterResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\CreateRecord\Concerns\Translatable;
use Dashed\DashedEcommerceCore\Filament\Resources\ProductFilterResource;

class CreateProductFilter extends CreateRecord
{
    use Translatable;

    protected static string $resource = ProductFilterResource::class;
}
