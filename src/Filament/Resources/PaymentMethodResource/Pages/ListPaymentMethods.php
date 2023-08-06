<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources\PaymentMethodResource\Pages;

use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Pages\ListRecords\Concerns\Translatable;
use Dashed\DashedEcommerceCore\Filament\Resources\PaymentMethodResource;

class ListPaymentMethods extends ListRecords
{
    use Translatable;

    protected static string $resource = PaymentMethodResource::class;
}
