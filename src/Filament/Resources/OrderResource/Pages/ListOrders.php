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
        $failedCount = collect(ecommerce()->shippingLabelProviders())
            ->sum(fn ($provider) => count($provider->failedOrders()));

        $actions = [CreateAction::make()];

        if ($failedCount > 0) {
            $actions[] = \Filament\Actions\Action::make('shippingLabelErrors')
                ->iconButton()
                ->color('danger')
                ->icon('heroicon-o-exclamation-triangle')
                ->label('Labels met fouten (' . $failedCount . ')')
                ->tooltip('Labels met fouten (' . $failedCount . ')')
                ->url(\Dashed\DashedEcommerceCore\Filament\Pages\ShippingLabelErrors::getUrl())
                ->openUrlInNewTab();
        }

        return array_merge($actions, ecommerce()->buttonActions('orders'));
    }

    protected function getFooterWidgets(): array
    {
        return [
            OrderOutstandingStatsWidget::class,
        ];
    }
}
