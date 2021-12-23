<?php

namespace Qubiqx\QcommerceEcommerceCore\Filament\Resources\ShippingMethodResource\Pages;

use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Pages\ListRecords\Concerns\Translatable;
use Qubiqx\QcommerceEcommerceCore\Filament\Resources\ShippingMethodResource;

class ListShippingMethods extends ListRecords
{
    use Translatable;

    protected static string $resource = ShippingMethodResource::class;
}
