<?php

namespace Qubiqx\QcommerceEcommerceCore\Filament\Resources\PaymentMethodResource\Pages;

use Qubiqx\QcommerceCore\Classes\Sites;
use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Pages\EditRecord\Concerns\Translatable;
use Qubiqx\QcommerceEcommerceCore\Filament\Resources\PaymentMethodResource;

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
