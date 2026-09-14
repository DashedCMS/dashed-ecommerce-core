<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources\OrderReturnResource\Pages;

use Filament\Resources\Pages\ListRecords;
use Dashed\DashedEcommerceCore\Filament\Resources\OrderReturnResource;
use Dashed\DashedEcommerceCore\Filament\Resources\OrderResource\Actions\RegisterReturnAction;

class ListOrderReturns extends ListRecords
{
    protected static string $resource = OrderReturnResource::class;

    protected function getHeaderActions(): array
    {
        return [
            RegisterReturnAction::withOrderPicker(),
        ];
    }
}
