<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources\PaymentMethodResource\Pages;

use Dashed\DashedCore\Classes\Sites;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\CreateRecord\Concerns\Translatable;
use Dashed\DashedEcommerceCore\Filament\Resources\PaymentMethodResource;

class CreatePaymentMethod extends CreateRecord
{
    use Translatable;

    protected static string $resource = PaymentMethodResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['site_id'] = $data['site_id'] ?? Sites::getFirstSite()['id'];

        return $data;
    }
}
