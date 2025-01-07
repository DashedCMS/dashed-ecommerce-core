<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources\FulfillmentCompanyResource\Pages;

use Dashed\DashedCore\Classes\Sites;
use Dashed\DashedEcommerceCore\Filament\Resources\FulfillmentCompanyResource;
use Filament\Actions\LocaleSwitcher;
use Filament\Resources\Pages\CreateRecord;
use Dashed\DashedEcommerceCore\Models\FulfillmentCompany;
use Filament\Resources\Pages\CreateRecord\Concerns\Translatable;

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
