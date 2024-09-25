<?php

namespace Dashed\DashedEcommerceCore;

use Dashed\DashedEcommerceCore\Filament\Pages\Settings\POSSettingsPage;
use Filament\Panel;
use Filament\Contracts\Plugin;
use Dashed\DashedEcommerceCore\Filament\Resources\OrderResource;
use Dashed\DashedEcommerceCore\Filament\Pages\POS\POSPageRedirect;
use Dashed\DashedEcommerceCore\Filament\Resources\ProductResource;
use Dashed\DashedEcommerceCore\Filament\Pages\Exports\ExportOrdersPage;
use Dashed\DashedEcommerceCore\Filament\Pages\Settings\VATSettingsPage;
use Dashed\DashedEcommerceCore\Filament\Resources\DiscountCodeResource;
use Dashed\DashedEcommerceCore\Filament\Resources\ShippingZoneResource;
use Dashed\DashedEcommerceCore\Filament\Resources\PaymentMethodResource;
use Dashed\DashedEcommerceCore\Filament\Resources\ProductFilterResource;
use Dashed\DashedEcommerceCore\Filament\Resources\ShippingClassResource;
use Dashed\DashedEcommerceCore\Filament\Pages\Exports\ExportInvoicesPage;
use Dashed\DashedEcommerceCore\Filament\Pages\Exports\ExportProductsPage;
use Dashed\DashedEcommerceCore\Filament\Pages\Settings\OrderSettingsPage;
use Dashed\DashedEcommerceCore\Filament\Resources\ShippingMethodResource;
use Dashed\DashedEcommerceCore\Filament\Resources\ProductCategoryResource;
use Dashed\DashedEcommerceCore\Filament\Widgets\Revenue\DailyRevenueStats;
use Dashed\DashedEcommerceCore\Filament\Pages\Settings\InvoiceSettingsPage;
use Dashed\DashedEcommerceCore\Filament\Pages\Settings\ProductSettingsPage;
use Dashed\DashedEcommerceCore\Filament\Widgets\Revenue\YearlyRevenueStats;
use Dashed\DashedEcommerceCore\Filament\Pages\Settings\CheckoutSettingsPage;
use Dashed\DashedEcommerceCore\Filament\Widgets\Revenue\AlltimeRevenueStats;
use Dashed\DashedEcommerceCore\Filament\Widgets\Revenue\MonthlyRevenueStats;
use Dashed\DashedEcommerceCore\Filament\Resources\ProductFilterOptionResource;
use Dashed\DashedEcommerceCore\Filament\Pages\Statistics\ProductStatisticsPage;
use Dashed\DashedEcommerceCore\Filament\Pages\Statistics\RevenueStatisticsPage;
use Dashed\DashedEcommerceCore\Filament\Pages\Statistics\DiscountStatisticsPage;
use Dashed\DashedEcommerceCore\Filament\Resources\ProductCharacteristicResource;
use Dashed\DashedEcommerceCore\Filament\Widgets\Revenue\DashboardFunLineChartStats;
use Dashed\DashedEcommerceCore\Filament\Widgets\Revenue\PaymentMethodPieChartWidget;
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
                RevenueStatisticsPage::class,
                ProductStatisticsPage::class,
                DiscountStatisticsPage::class,
                POSPageRedirect::class,
                POSSettingsPage::class,
            ])
            ->widgets([
                MonthlyRevenueAndReturnLineChartStats::class,
                DailyRevenueStats::class,
                MonthlyRevenueStats::class,
                YearlyRevenueStats::class,
                AlltimeRevenueStats::class,
                PaymentMethodPieChartWidget::class,
                DashboardFunLineChartStats::class,
            ])
            ->resources([
                PaymentMethodResource::class,
                ShippingClassResource::class,
                ShippingZoneResource::class,
                ShippingMethodResource::class,
                DiscountCodeResource::class,
                ProductResource::class,
                ProductCategoryResource::class,
                ProductFilterResource::class,
                ProductFilterOptionResource::class,
                ProductCharacteristicResource::class,
                OrderResource::class,
            ]);
    }

    public function boot(Panel $panel): void
    {

    }
}
