<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources\PricePerUserResource\Pages;

use Filament\Resources\Pages\ListRecords;
use Dashed\DashedEcommerceCore\Filament\Resources\PricePerUserResource;

class ListPricePerUser extends ListRecords
{
    protected static string $resource = PricePerUserResource::class;

    protected function getHeaderActions(): array
    {
        return [
        ];
    }
}
