<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources\ProductFilterResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Actions\LocaleSwitcher;
use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Pages\EditRecord\Concerns\Translatable;
use Dashed\DashedEcommerceCore\Filament\Resources\ProductFilterResource;

class EditProductFilter extends EditRecord
{
    use Translatable;

    protected static string $resource = ProductFilterResource::class;

    protected function getHeaderActions(): array
    {
        return [
            LocaleSwitcher::make(),
            DeleteAction::make(),
        ];
    }
}
