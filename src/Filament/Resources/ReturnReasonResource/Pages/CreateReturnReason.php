<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources\ReturnReasonResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use LaraZeus\SpatieTranslatable\Actions\LocaleSwitcher;
use Dashed\DashedEcommerceCore\Filament\Resources\ReturnReasonResource;
use LaraZeus\SpatieTranslatable\Resources\Pages\CreateRecord\Concerns\Translatable;

class CreateReturnReason extends CreateRecord
{
    use Translatable;

    protected static string $resource = ReturnReasonResource::class;

    protected function getActions(): array
    {
        return [
            LocaleSwitcher::make(),
        ];
    }
}
