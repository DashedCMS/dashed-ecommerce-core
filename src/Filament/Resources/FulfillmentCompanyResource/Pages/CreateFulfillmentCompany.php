<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources\FulfillmentCompanyResource\Pages;

use Dashed\DashedCore\Classes\Sites;
use Filament\Actions\LocaleSwitcher;
use Filament\Resources\Pages\CreateRecord;
use Dashed\DashedEcommerceCore\Models\FulfillmentCompany;
use Filament\Resources\Pages\CreateRecord\Concerns\Translatable;

class CreateFulfillmentCompany extends CreateRecord
{
    use Translatable;

    protected static string $resource = FulfillmentCompany::class;

    protected function getActions(): array
    {
        return [
          LocaleSwitcher::make(),
        ];
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['site_id'] = $data['site_id'] ?? Sites::getFirstSite()['id'];

        return $data;
    }
}
