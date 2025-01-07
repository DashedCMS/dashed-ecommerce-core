<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources\FulfillmentCompanyResource\Pages;

use Dashed\DashedCore\Classes\Sites;
use Dashed\DashedEcommerceCore\Models\FulfillmentCompany;
use Filament\Actions\LocaleSwitcher;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\CreateRecord\Concerns\Translatable;
use Dashed\DashedEcommerceCore\Filament\Resources\PaymentMethodResource;

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
