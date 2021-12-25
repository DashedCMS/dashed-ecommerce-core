<?php

namespace Qubiqx\QcommerceEcommerceCore\Filament\Resources\DiscountCodeResource\Pages;

use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Pages\EditRecord\Concerns\Translatable;
use Qubiqx\QcommerceEcommerceCore\Filament\Resources\DiscountCodeResource;

class EditDiscountCode extends EditRecord
{
    use Translatable;

    protected static string $resource = DiscountCodeResource::class;
}
