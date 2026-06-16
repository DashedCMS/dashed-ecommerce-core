<?php

namespace Dashed\DashedEcommerceCore;

use Filament\Panel;
use Filament\Actions\Action;
use Filament\Contracts\Plugin;
use Illuminate\Support\Facades\Artisan;
use Filament\Notifications\Notification;
use Dashed\DashedEcommerceCore\Filament\Resources\CartResource;
use Dashed\DashedEcommerceCore\Filament\Resources\OrderResource;
use Dashed\DashedEcommerceCore\Filament\Resources\OrderReturnResource;
use Dashed\DashedEcommerceCore\Filament\Resources\ReturnReasonResource;
use Dashed\DashedEcommerceCore\Exports\ExportFinancialReportPage;
use Dashed\DashedEcommerceCore\Filament\Widgets\PrintQueueWidget;
use Dashed\DashedEcommerceCore\Filament\Pages\POS\POSPageRedirect;
use Dashed\DashedEcommerceCore\Filament\Resources\PrinterResource;
use Dashed\DashedEcommerceCore\Filament\Resources\ProductResource;
use Dashed\DashedEcommerceCore\Filament\Resources\GiftcardResource;
use Dashed\DashedEcommerceCore\Filament\Resources\PrintJobResource;
use Dashed\DashedEcommerceCore\Filament\Resources\PriceGroupResource;
use Dashed\DashedEcommerceCore\Filament\Resources\ProductFaqResource;
use Dashed\DashedEcommerceCore\Filament\Resources\ProductFinderResource;
use Dashed\DashedEcommerceCore\Filament\Resources\ProductTabResource;
use Dashed\DashedEcommerceCore\Filament\Widgets\Revenue\RevenueStats;
use Dashed\DashedEcommerceCore\Filament\Pages\Exports\ExportOrdersPage;
use Dashed\DashedEcommerceCore\Filament\Pages\Settings\Gs1SettingsPage;
use Dashed\DashedEcommerceCore\Filament\Pages\Settings\POSSettingsPage;
use Dashed\DashedEcommerceCore\Filament\Pages\Settings\VATSettingsPage;
use Dashed\DashedEcommerceCore\Filament\Resources\DiscountCodeResource;
use Dashed\DashedEcommerceCore\Filament\Resources\PricePerUserResource;
use Dashed\DashedEcommerceCore\Filament\Resources\ProductExtraResource;
use Dashed\DashedEcommerceCore\Filament\Resources\ProductGroupResource;
use Dashed\DashedEcommerceCore\Filament\Resources\ShippingZoneResource;
use Dashed\DashedEcommerceCore\Filament\Widgets\Dashboard\SoldoutCount;
use Dashed\DashedEcommerceCore\Filament\Resources\PaymentMethodResource;
use Dashed\DashedEcommerceCore\Filament\Resources\ProductFilterResource;
use Dashed\DashedEcommerceCore\Filament\Resources\ShippingClassResource;
use Dashed\DashedEcommerceCore\Filament\Pages\Exports\ExportInvoicesPage;
use Dashed\DashedEcommerceCore\Filament\Pages\Exports\ExportProductsPage;
use Dashed\DashedEcommerceCore\Filament\Pages\Settings\OrderSettingsPage;
use Dashed\DashedEcommerceCore\Filament\Resources\ShippingMethodResource;
use Dashed\DashedEcommerceCore\Filament\Widgets\Dashboard\CartStatistics;
use Dashed\DashedEcommerceCore\Filament\Pages\POS\POSCustomerPageRedirect;
use Dashed\DashedEcommerceCore\Filament\Resources\ProductCategoryResource;
use Dashed\DashedEcommerceCore\Filament\Pages\Settings\InvoiceSettingsPage;
use Dashed\DashedEcommerceCore\Filament\Pages\Settings\ProductSettingsPage;
use Dashed\DashedEcommerceCore\Filament\Resources\OrderHandledFlowResource;
use Dashed\DashedEcommerceCore\Filament\Resources\OrderLogTemplateResource;
use Dashed\DashedEcommerceCore\Filament\Widgets\Revenue\YearlyRevenueStats;
use Dashed\DashedEcommerceCore\Filament\Pages\Settings\CheckoutSettingsPage;
use Dashed\DashedEcommerceCore\Filament\Resources\AbandonedCartFlowResource;
use Dashed\DashedEcommerceCore\Filament\Widgets\Revenue\AlltimeRevenueStats;
use Dashed\DashedEcommerceCore\Filament\Widgets\Revenue\MonthlyRevenueStats;
use Dashed\DashedEcommerceCore\Filament\Resources\FulfillmentCompanyResource;
use Dashed\DashedEcommerceCore\Filament\Pages\Settings\PrintQueueSettingsPage;
use Dashed\DashedEcommerceCore\Filament\Resources\ProductFilterOptionResource;
use Dashed\DashedEcommerceCore\Filament\Pages\Settings\OrderCancelSettingsPage;
use Dashed\DashedEcommerceCore\Filament\Pages\Settings\ReturnSettingsPage;
use Dashed\DashedEcommerceCore\Filament\Pages\Statistics\ActionsStatisticsPage;
use Dashed\DashedEcommerceCore\Filament\Pages\Statistics\ProductStatisticsPage;
use Dashed\DashedEcommerceCore\Filament\Pages\Statistics\RevenueStatisticsPage;
use Dashed\DashedEcommerceCore\Filament\Pages\Statistics\DiscountStatisticsPage;
use Dashed\DashedEcommerceCore\Filament\Resources\ProductCharacteristicResource;
use Dashed\DashedEcommerceCore\Filament\Pages\Settings\CustomerMatchSettingsPage;
use Dashed\DashedEcommerceCore\Filament\Widgets\Revenue\CartActionsPieChartWidget;
use Dashed\DashedEcommerceCore\Filament\Pages\Statistics\AttributionStatisticsPage;
use Dashed\DashedEcommerceCore\Filament\Widgets\Orders\OrderOutstandingStatsWidget;
use Dashed\DashedEcommerceCore\Filament\Widgets\Revenue\DashboardFunLineChartStats;
use Dashed\DashedEcommerceCore\Filament\Pages\Settings\DefaultEcommerceSettingsPage;
use Dashed\DashedEcommerceCore\Filament\Pages\Statistics\ProductGroupStatisticsPage;
use Dashed\DashedEcommerceCore\Filament\Widgets\Revenue\PaymentMethodPieChartWidget;
use Dashed\DashedEcommerceCore\Filament\Widgets\Statistics\OrderAttributionStatsWidget;
use Dashed\DashedEcommerceCore\Filament\Resources\OpenOrderProducts\OpenOrderProductResource;
use Dashed\DashedEcommerceCore\Filament\Widgets\Revenue\MonthlyRevenueAndReturnLineChartStats;

class DashedEcommerceCorePlugin implements Plugin
{
    public function getId(): string
    {
        return 'dashed-ecommerce-core';
    }

    public function register(Panel $panel): void
    {
        $panel
            ->pages([
                InvoiceSettingsPage::class,
                OrderSettingsPage::class,
                CheckoutSettingsPage::class,
                ProductSettingsPage::class,
                VATSettingsPage::class,
                ExportInvoicesPage::class,
                ExportOrdersPage::class,
                ExportProductsPage::class,
                ExportFinancialReportPage::class,
                RevenueStatisticsPage::class,
                ProductStatisticsPage::class,
                DiscountStatisticsPage::class,
                POSPageRedirect::class,
                POSSettingsPage::class,
                POSCustomerPageRedirect::class,
                ProductGroupStatisticsPage::class,
                ActionsStatisticsPage::class,
                AttributionStatisticsPage::class,
                DefaultEcommerceSettingsPage::class,
                OrderCancelSettingsPage::class,
                ReturnSettingsPage::class,
                CustomerMatchSettingsPage::class,
                Gs1SettingsPage::class,
                PrintQueueSettingsPage::class,
                \Dashed\DashedEcommerceCore\Filament\Pages\RecommendationsDebugPage::class,
                \Dashed\DashedEcommerceCore\Filament\Pages\ShippingLabelErrors::class,
                \Dashed\DashedEcommerceCore\Filament\Pages\InsightsPage::class,
                \Dashed\DashedEcommerceCore\Filament\Pages\Settings\DoelenSettingsPage::class,
            ])
            ->widgets([
                MonthlyRevenueAndReturnLineChartStats::class,
                RevenueStats::class,
//                MonthlyRevenueStats::class,
//                YearlyRevenueStats::class,
//                AlltimeRevenueStats::class,
                CartActionsPieChartWidget::class,
                PaymentMethodPieChartWidget::class,
                DashboardFunLineChartStats::class,
                SoldoutCount::class,
                CartStatistics::class,
                OrderAttributionStatsWidget::class,
                PrintQueueWidget::class,
                OrderOutstandingStatsWidget::class,
            ])
            ->resources([
                PaymentMethodResource::class,
                OrderLogTemplateResource::class,
                ShippingClassResource::class,
                ShippingZoneResource::class,
                ShippingMethodResource::class,
                DiscountCodeResource::class,
                ProductFaqResource::class,
                ProductFinderResource::class,
                ProductResource::class,
                ProductCategoryResource::class,
                ProductFilterResource::class,
                ProductFilterOptionResource::class,
                ProductCharacteristicResource::class,
                OrderResource::class,
                OrderReturnResource::class,
                ReturnReasonResource::class,
                OpenOrderProductResource::class,
                ProductTabResource::class,
                ProductExtraResource::class,
                ProductGroupResource::class,
                PricePerUserResource::class,
                PriceGroupResource::class,
                FulfillmentCompanyResource::class,
                GiftcardResource::class,
                CartResource::class,
                AbandonedCartFlowResource::class,
                OrderHandledFlowResource::class,
                PrinterResource::class,
                PrintJobResource::class,
            ]);
    }

    public function boot(Panel $panel): void
    {
        // productPriceFields is registered in DashedEcommerceCoreServiceProvider::bootingPackage()
        // so it is available in every context (incl. the queued product import worker),
        // not only when a Filament panel boots.

        ecommerce()->buttonActions(
            'orders',
            array_merge(ecommerce()->buttonActions('orders'), [
                Action::make('syncShippingStatuses')
                    ->iconButton()
                    ->color('gray')
                    ->icon('heroicon-o-arrow-path')
                    ->label('Verzendstatussen ophalen')
                    ->tooltip('Verzendstatussen ophalen bij alle verzendkoppelingen')
                    ->visible(fn () => count(ecommerce()->shippingStatusCommands()) > 0)
                    ->requiresConfirmation()
                    ->modalHeading('Verzendstatussen synchroniseren')
                    ->modalDescription('Hiermee wordt voor elke niet-afgehandelde bestelling de huidige status bij alle gekoppelde vervoerders opgehaald en bijgewerkt. De sync draait in de achtergrond.')
                    ->modalSubmitActionLabel('Sync starten')
                    ->action(function () {
                        foreach (ecommerce()->shippingStatusCommands() as $command) {
                            Artisan::queue($command)->onQueue('ecommerce');
                        }

                        Notification::make()
                            ->title('Sync gestart')
                            ->body('De verzendstatussen worden in de achtergrond opgehaald.')
                            ->success()
                            ->send();
                    }),
            ])
        );
    }
}
