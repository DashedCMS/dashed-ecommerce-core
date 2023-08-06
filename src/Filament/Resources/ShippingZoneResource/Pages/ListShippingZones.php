<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources\ShippingZoneResource\Pages;

use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Pages\ListRecords\Concerns\Translatable;
use Dashed\DashedEcommerceCore\Filament\Resources\ShippingZoneResource;

class ListShippingZones extends ListRecords
{
    use Translatable;

    protected static string $resource = ShippingZoneResource::class;
}
