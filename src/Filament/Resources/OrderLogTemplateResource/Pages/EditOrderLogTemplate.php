<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources\OrderLogTemplateResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use LaraZeus\SpatieTranslatable\Actions\LocaleSwitcher;
use Dashed\DashedEcommerceCore\Filament\Resources\OrderLogTemplateResource;
use LaraZeus\SpatieTranslatable\Resources\Pages\EditRecord\Concerns\Translatable;

class EditOrderLogTemplate extends EditRecord
{
    use Translatable;

    protected static string $resource = OrderLogTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            LocaleSwitcher::make(),
            DeleteAction::make(),
        ];
    }
}
