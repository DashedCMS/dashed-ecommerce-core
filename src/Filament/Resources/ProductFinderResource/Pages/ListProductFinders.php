<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources\ProductFinderResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Dashed\DashedEcommerceCore\Filament\Resources\ProductFinderResource;

class ListProductFinders extends ListRecords
{
    protected static string $resource = ProductFinderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
