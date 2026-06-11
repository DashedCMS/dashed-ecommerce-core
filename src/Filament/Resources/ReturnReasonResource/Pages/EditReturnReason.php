<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources\ReturnReasonResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use LaraZeus\SpatieTranslatable\Actions\LocaleSwitcher;
use Dashed\DashedEcommerceCore\Filament\Resources\ReturnReasonResource;
use LaraZeus\SpatieTranslatable\Resources\Pages\EditRecord\Concerns\Translatable;

class EditReturnReason extends EditRecord
{
    use Translatable;

    protected static string $resource = ReturnReasonResource::class;

    protected function getHeaderActions(): array
    {
        return [
            LocaleSwitcher::make(),
            DeleteAction::make(),
        ];
    }
}
