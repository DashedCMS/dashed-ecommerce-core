<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources\OrderResource\Pages;

use Filament\Support\Enums\Width;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Dashed\DashedEcommerceCore\Filament\Resources\OrderResource;
use Dashed\DashedEcommerceCore\Filament\Widgets\Orders\OrderOutstandingStatsWidget;

class ListOrders extends ListRecords
{
    protected static string $resource = OrderResource::class;

    protected Width | string | null $maxContentWidth = 'full';

    protected function getHeaderActions(): array
    {
        return array_merge([
            CreateAction::make(),
        ], ecommerce()->buttonActions('orders'));
    }

    protected function getFooterWidgets(): array
    {
        return [
            OrderOutstandingStatsWidget::class,
        ];
    }
}
