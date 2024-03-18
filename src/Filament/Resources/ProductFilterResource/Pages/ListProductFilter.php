<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources\ProductFilterResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Actions\LocaleSwitcher;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Pages\ListRecords\Concerns\Translatable;
use Dashed\DashedEcommerceCore\Filament\Resources\ProductFilterResource;

class ListProductFilter extends ListRecords
{
    use Translatable;

    protected static string $resource = ProductFilterResource::class;

    protected function getHeaderActions(): array
    {
        return [
            LocaleSwitcher::make(),
            CreateAction::make(),
        ];
    }
}
