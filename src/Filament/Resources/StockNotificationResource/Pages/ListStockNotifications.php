<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources\StockNotificationResource\Pages;

use Filament\Resources\Pages\ListRecords;
use Dashed\DashedEcommerceCore\Filament\Resources\StockNotificationResource;

class ListStockNotifications extends ListRecords
{
    protected static string $resource = StockNotificationResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
