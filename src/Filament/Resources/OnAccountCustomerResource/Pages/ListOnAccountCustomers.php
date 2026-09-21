<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources\OnAccountCustomerResource\Pages;

use Filament\Resources\Pages\ListRecords;
use Dashed\DashedEcommerceCore\Filament\Resources\OnAccountCustomerResource;

class ListOnAccountCustomers extends ListRecords
{
    protected static string $resource = OnAccountCustomerResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
