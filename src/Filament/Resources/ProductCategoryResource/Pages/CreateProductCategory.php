<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources\ProductCategoryResource\Pages;

use Dashed\DashedCore\Filament\Concerns\HasCreatableCMSActions;
use Illuminate\Support\Str;
use Dashed\DashedCore\Classes\Sites;
use Filament\Actions\LocaleSwitcher;
use Filament\Resources\Pages\CreateRecord;
use Dashed\DashedEcommerceCore\Models\ProductCategory;
use Filament\Resources\Pages\CreateRecord\Concerns\Translatable;
use Dashed\DashedEcommerceCore\Filament\Resources\ProductCategoryResource;

class CreateProductCategory extends CreateRecord
{
    use HasCreatableCMSActions;

    protected static string $resource = ProductCategoryResource::class;

    protected function getActions(): array
    {
        return self::CMSActions();
    }
}
