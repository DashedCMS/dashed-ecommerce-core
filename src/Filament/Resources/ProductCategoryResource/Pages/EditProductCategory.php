<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources\ProductCategoryResource\Pages;

use Filament\Resources\Pages\EditRecord;
use Dashed\DashedCore\Filament\Concerns\HasEditableCMSActions;
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
