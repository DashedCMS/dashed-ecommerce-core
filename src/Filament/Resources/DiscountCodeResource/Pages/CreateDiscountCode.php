<?php

namespace Qubiqx\QcommerceEcommerceCore\Filament\Resources\DiscountCodeResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\CreateRecord\Concerns\Translatable;
use Qubiqx\QcommerceEcommerceCore\Filament\Resources\DiscountCodeResource;

class CreateDiscountCode extends CreateRecord
{
    use Translatable;

    protected static string $resource = DiscountCodeResource::class;
}
