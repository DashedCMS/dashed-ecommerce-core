<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources\ProductGroupResource\Pages;

use Dashed\DashedEcommerceCore\Filament\Resources\ProductGroupResource;
use Dashed\DashedEcommerceCore\Models\Product;
use Filament\Actions\CreateAction;
use Filament\Actions\LocaleSwitcher;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Pages\ListRecords\Concerns\Translatable;
use Dashed\DashedEcommerceCore\Filament\Resources\ProductResource;
use Illuminate\Database\Eloquent\Builder;

class ListProductGroups extends ListRecords
{
    use Translatable;

    protected static string $resource = ProductGroupResource::class;

    protected ?string $maxContentWidth = 'full';

    protected function getHeaderActions(): array
    {
        return [
            LocaleSwitcher::make(),
            CreateAction::make(),
        ];
    }
}
