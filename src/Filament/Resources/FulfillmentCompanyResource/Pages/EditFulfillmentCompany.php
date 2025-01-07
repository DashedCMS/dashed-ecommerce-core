<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources\FulfillmentCompanyResource\Pages;

use Dashed\DashedEcommerceCore\Filament\Resources\FulfillmentCompanyResource;
use Filament\Actions\DeleteAction;
use Dashed\DashedCore\Classes\Sites;
use Filament\Actions\LocaleSwitcher;
use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Pages\EditRecord\Concerns\Translatable;
use Dashed\DashedEcommerceCore\Filament\Resources\PaymentMethodResource;

class EditFulfillmentCompany extends EditRecord
{
    use Translatable;

    protected static string $resource = FulfillmentCompanyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            LocaleSwitcher::make(),
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['site_id'] = $data['site_id'] ?? Sites::getFirstSite()['id'];

        return $data;
    }
}
