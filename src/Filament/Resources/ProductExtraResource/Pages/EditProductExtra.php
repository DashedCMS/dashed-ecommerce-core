<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources\ProductExtraResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Actions\LocaleSwitcher;
use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Pages\EditRecord\Concerns\Translatable;
use Dashed\DashedEcommerceCore\Filament\Resources\ProductExtraResource;

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
