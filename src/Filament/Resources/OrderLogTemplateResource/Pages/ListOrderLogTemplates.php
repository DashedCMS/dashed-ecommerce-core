<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources\OrderLogTemplateResource\Pages;

use Dashed\DashedEcommerceCore\Filament\Resources\OrderLogTemplateResource;
use Filament\Actions\CreateAction;
use Filament\Actions\LocaleSwitcher;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Pages\ListRecords\Concerns\Translatable;
use Dashed\DashedEcommerceCore\Filament\Resources\PaymentMethodResource;

class ListOrderLogTemplates extends ListRecords
{
    use Translatable;

    protected static string $resource = OrderLogTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            LocaleSwitcher::make(),
            CreateAction::make(),
        ];
    }
}
