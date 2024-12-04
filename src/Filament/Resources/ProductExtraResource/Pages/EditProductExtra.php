<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources\ProductExtraResource\Pages;

use Dashed\DashedEcommerceCore\Filament\Resources\ProductExtraResource;
use Dashed\DashedEcommerceCore\Filament\Resources\ProductTabResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\LocaleSwitcher;
use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Pages\EditRecord\Concerns\Translatable;
use Dashed\DashedEcommerceCore\Filament\Resources\ProductFilterResource;

class EditProductExtra extends EditRecord
{
    use Translatable;

    protected static string $resource = ProductExtraResource::class;

    protected function getHeaderActions(): array
    {
        return [
            LocaleSwitcher::make(),
            DeleteAction::make(),
        ];
    }
}
