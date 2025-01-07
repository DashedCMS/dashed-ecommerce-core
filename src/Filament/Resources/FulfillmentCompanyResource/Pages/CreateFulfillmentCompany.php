<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources\FulfillmentCompanyResource\Pages;

use Filament\Actions\LocaleSwitcher;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\CreateRecord\Concerns\Translatable;
use Dashed\DashedEcommerceCore\Filament\Resources\FulfillmentCompanyResource;

class CreateFulfillmentCompany extends CreateRecord
{
    //    use Translatable;

    protected static string $resource = FulfillmentCompanyResource::class;

    protected function getActions(): array
    {
        return [
//          LocaleSwitcher::make(),
        ];
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $data;
    }
}
