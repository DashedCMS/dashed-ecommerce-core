<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources\ProductFinderResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Dashed\DashedEcommerceCore\Filament\Resources\ProductFinderResource;

class EditProductFinder extends EditRecord
{
    protected static string $resource = ProductFinderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
