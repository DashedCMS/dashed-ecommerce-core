<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources\ProductCategoryResource\Pages;

use Dashed\DashedCore\Filament\Concerns\HasEditableCMSActions;
use Illuminate\Support\Str;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Dashed\DashedCore\Classes\Sites;
use Filament\Actions\LocaleSwitcher;
use Filament\Resources\Pages\EditRecord;
use Dashed\DashedEcommerceCore\Models\ProductCategory;
use Filament\Resources\Pages\EditRecord\Concerns\Translatable;
use Dashed\DashedEcommerceCore\Filament\Resources\ProductCategoryResource;

class EditProductCategory extends EditRecord
{
    use HasEditableCMSActions;

    protected static string $resource = ProductCategoryResource::class;

    protected function getActions(): array
    {
        return self::CMSActions();
    }
}
