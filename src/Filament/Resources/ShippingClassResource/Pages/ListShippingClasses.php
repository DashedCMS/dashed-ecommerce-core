<?php

namespace Qubiqx\QcommerceEcommerceCore\Filament\Resources\ShippingClassResource\Pages;

use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Pages\ListRecords\Concerns\Translatable;
use Qubiqx\QcommerceEcommerceCore\Filament\Resources\ShippingClassResource;

class ListShippingClasses extends ListRecords
{
    use Translatable;

    protected static string $resource = ShippingClassResource::class;
}
