<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources\ProductExtraResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Actions\LocaleSwitcher;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Pages\ListRecords\Concerns\Translatable;
use Dashed\DashedEcommerceCore\Filament\Resources\ProductExtraResource;

class ListProductExtra extends ListRecords
{
    use Translatable;

    protected static string $resource = ProductExtraResource::class;

    protected function getHeaderActions(): array
    {
        return [
            LocaleSwitcher::make(),
            CreateAction::make(),
        ];
    }
}
