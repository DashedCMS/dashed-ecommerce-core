<?php

namespace Qubiqx\QcommerceEcommerceCore\Filament\Resources\ShippingZoneResource\Pages;

use Qubiqx\QcommerceCore\Classes\Sites;
use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Pages\EditRecord\Concerns\Translatable;
use Qubiqx\QcommerceEcommerceCore\Filament\Resources\ShippingZoneResource;

class EditShippingZone extends EditRecord
{
    use Translatable;

    protected static string $resource = ShippingZoneResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['site_id'] = $data['site_id'] ?? Sites::getFirstSite()['id'];

        return $data;
    }
}
