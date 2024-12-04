<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources\ProductTabResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Actions\LocaleSwitcher;
use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Pages\EditRecord\Concerns\Translatable;
use Dashed\DashedEcommerceCore\Filament\Resources\ProductTabResource;

class EditProductTab extends EditRecord
{
    use Translatable;

    protected static string $resource = ProductTabResource::class;

    protected function getHeaderActions(): array
    {
        return [
            LocaleSwitcher::make(),
            DeleteAction::make(),
        ];
    }
}
