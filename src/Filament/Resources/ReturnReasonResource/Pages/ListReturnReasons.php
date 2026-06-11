<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources\ReturnReasonResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use LaraZeus\SpatieTranslatable\Actions\LocaleSwitcher;
use Dashed\DashedEcommerceCore\Filament\Resources\ReturnReasonResource;
use LaraZeus\SpatieTranslatable\Resources\Pages\ListRecords\Concerns\Translatable;

class ListReturnReasons extends ListRecords
{
    use Translatable;

    protected static string $resource = ReturnReasonResource::class;

    protected function getHeaderActions(): array
    {
        return [
            LocaleSwitcher::make(),
            CreateAction::make(),
        ];
    }
}
