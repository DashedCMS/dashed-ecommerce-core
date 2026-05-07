<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources\ProductCategoryResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use LaraZeus\SpatieTranslatable\Actions\LocaleSwitcher;
use Dashed\DashedCore\Filament\Concerns\HasNestableSortingAction;
use Dashed\DashedEcommerceCore\Filament\Resources\ProductCategoryResource;
use LaraZeus\SpatieTranslatable\Resources\Pages\ListRecords\Concerns\Translatable;

class ListProductCategory extends ListRecords
{
    use HasNestableSortingAction;
    use Translatable;

    protected static string $resource = ProductCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return array_values(array_filter([
            $this->getNestableSortingHeaderAction(),
            LocaleSwitcher::make(),
            CreateAction::make(),
        ]));
    }
}
