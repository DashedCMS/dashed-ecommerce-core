<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources\PricePerUserResource\Pages;

use Dashed\DashedEcommerceCore\Filament\Resources\PricePerUserResource;
use Filament\Actions\CreateAction;
use Filament\Actions\LocaleSwitcher;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Pages\ListRecords\Concerns\Translatable;
use Dashed\DashedEcommerceCore\Filament\Resources\ProductTabResource;

class ListPricePerUser extends ListRecords
{

    protected static string $resource = PricePerUserResource::class;

    protected function getHeaderActions(): array
    {
        return [
        ];
    }
}
