<?php

namespace Qubiqx\QcommerceEcommerceCore\Filament\Resources\ShippingClassResource\Pages;

use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Pages\EditRecord\Concerns\Translatable;
use Qubiqx\QcommerceEcommerceCore\Filament\Resources\ShippingClassResource;

class EditShippingClass extends EditRecord
{
    use Translatable;

    protected static string $resource = ShippingClassResource::class;
}
