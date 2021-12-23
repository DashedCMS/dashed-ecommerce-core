<?php

namespace Qubiqx\QcommerceEcommerceCore\Filament\Resources\ShippingZoneResource\Pages;

use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Pages\ListRecords\Concerns\Translatable;
use Qubiqx\QcommerceEcommerceCore\Filament\Resources\ShippingZoneResource;

class ListShippingZones extends ListRecords
{
    use Translatable;

    protected static string $resource = ShippingZoneResource::class;
}
