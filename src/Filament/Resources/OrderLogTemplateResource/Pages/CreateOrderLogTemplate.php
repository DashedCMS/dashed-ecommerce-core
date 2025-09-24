<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources\OrderLogTemplateResource\Pages;

use Dashed\DashedCore\Classes\Sites;
use Dashed\DashedEcommerceCore\Filament\Resources\OrderLogTemplateResource;
use Filament\Actions\LocaleSwitcher;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\CreateRecord\Concerns\Translatable;
use Dashed\DashedEcommerceCore\Filament\Resources\PaymentMethodResource;

class CreateOrderLogTemplate extends CreateRecord
{
    use Translatable;

    protected static string $resource = OrderLogTemplateResource::class;

    protected function getActions(): array
    {
        return [
          LocaleSwitcher::make(),
        ];
    }
}
