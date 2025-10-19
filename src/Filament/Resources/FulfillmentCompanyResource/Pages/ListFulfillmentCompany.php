<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources\FulfillmentCompanyResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use LaraZeus\SpatieTranslatable\Actions\LocaleSwitcher;
use Dashed\DashedEcommerceCore\Filament\Resources\FulfillmentCompanyResource;
use LaraZeus\SpatieTranslatable\Resources\Pages\ListRecords\Concerns\Translatable;

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
