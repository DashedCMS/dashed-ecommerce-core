<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources\FulfillmentCompanyResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Actions\LocaleSwitcher;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Pages\ListRecords\Concerns\Translatable;
use Dashed\DashedEcommerceCore\Filament\Resources\FulfillmentCompanyResource;

class ListFulfillmentCompany extends ListRecords
{
//    use Translatable;

    protected static string $resource = FulfillmentCompanyResource::class;

    protected function getHeaderActions(): array
    {
        return [
//            LocaleSwitcher::make(),
            CreateAction::make(),
        ];
    }
}
