<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources\PaymentMethodResource\Pages;

use Dashed\DashedCore\Classes\Sites;
use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Pages\EditRecord\Concerns\Translatable;
use Dashed\DashedEcommerceCore\Filament\Resources\PaymentMethodResource;

class EditPaymentMethod extends EditRecord
{
    use Translatable;

    protected static string $resource = PaymentMethodResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['site_id'] = $data['site_id'] ?? Sites::getFirstSite()['id'];

        return $data;
    }
}
