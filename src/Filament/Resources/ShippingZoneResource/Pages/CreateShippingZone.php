<?php

namespace Qubiqx\QcommerceEcommerceCore\Filament\Resources\ShippingZoneResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\CreateRecord\Concerns\Translatable;
use Qubiqx\QcommerceEcommerceCore\Filament\Resources\ShippingZoneResource;

class CreateShippingZone extends CreateRecord
{
    use Translatable;

    protected static string $resource = ShippingZoneResource::class;
}
