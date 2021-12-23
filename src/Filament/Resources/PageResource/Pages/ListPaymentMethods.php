<?php

namespace Qubiqx\QcommerceEcommerceCore\Filament\Resources\PageResource\Pages;

use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Pages\ListRecords\Concerns\Translatable;
use Qubiqx\QcommerceEcommerceCore\Filament\Resources\PaymentMethodResource;

class ListPaymentMethods extends ListRecords
{
    use Translatable;

    protected static string $resource = PaymentMethodResource::class;
}
