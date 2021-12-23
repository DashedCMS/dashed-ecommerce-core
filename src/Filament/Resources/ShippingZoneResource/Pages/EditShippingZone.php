<?php

namespace Qubiqx\QcommerceEcommerceCore\Filament\Resources\ShippingZoneResource\Pages;

use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Pages\EditRecord\Concerns\Translatable;
use Qubiqx\QcommerceEcommerceCore\Filament\Resources\ShippingClassResource;
use Qubiqx\QcommerceEcommerceCore\Filament\Resources\ShippingZoneResource;

class EditShippingZone extends EditRecord
{
    use Translatable;

    protected static string $resource = ShippingZoneResource::class;
}
