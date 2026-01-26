<?php

namespace Dashed\DashedEcommerceCore;

use Dashed\DashedEcommerceCore\Filament\Pages\Settings\OrderCancelSettingsPage;
use Filament\Panel;
use Filament\Contracts\Plugin;
use Dashed\DashedEcommerceCore\Filament\Resources\OrderResource;
use Dashed\DashedEcommerceCore\Exports\ExportFinancialReportPage;
use Dashed\DashedEcommerceCore\Filament\Pages\POS\POSPageRedirect;
use Dashed\DashedEcommerceCore\Filament\Resources\ProductResource;
use Dashed\DashedEcommerceCore\Filament\Resources\GiftcardResource;
use Dashed\DashedEcommerceCore\Filament\Resources\ProductFaqResource;
use Dashed\DashedEcommerceCore\Filament\Resources\ProductTabResource;
use Dashed\DashedEcommerceCore\Filament\Widgets\Revenue\RevenueStats;
use Dashed\DashedEcommerceCore\Filament\Pages\Exports\ExportOrdersPage;
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
use Dashed\DashedEcommerceCore\Filament\Pages\POS\POSCustomerPageRedirect;
use Dashed\DashedEcommerceCore\Filament\Resources\ProductCategoryResource;
use Dashed\DashedEcommerceCore\Filament\Pages\Settings\InvoiceSettingsPage;
use Dashed\DashedEcommerceCore\Filament\Pages\Settings\ProductSettingsPage;
use Dashed\DashedEcommerceCore\Filament\Resources\OrderLogTemplateResource;
use Dashed\DashedEcommerceCore\Filament\Widgets\Revenue\YearlyRevenueStats;
use Dashed\DashedEcommerceCore\Filament\Pages\Settings\CheckoutSettingsPage;
use Dashed\DashedEcommerceCore\Filament\Widgets\Revenue\AlltimeRevenueStats;
use Dashed\DashedEcommerceCore\Filament\Widgets\Revenue\MonthlyRevenueStats;
use Dashed\DashedEcommerceCore\Filament\Resources\FulfillmentCompanyResource;
use Dashed\DashedEcommerceCore\Filament\Resources\ProductFilterOptionResource;
use Dashed\DashedEcommerceCore\Filament\Pages\Statistics\ActionsStatisticsPage;
use Dashed\DashedEcommerceCore\Filament\Pages\Statistics\ProductStatisticsPage;
use Dashed\DashedEcommerceCore\Filament\Pages\Statistics\RevenueStatisticsPage;
use Dashed\DashedEcommerceCore\Filament\Pages\Statistics\DiscountStatisticsPage;
use Dashed\DashedEcommerceCore\Filament\Resources\ProductCharacteristicResource;
use Dashed\DashedEcommerceCore\Filament\Widgets\Revenue\CartActionsPieChartWidget;
use Dashed\DashedEcommerceCore\Filament\Widgets\Revenue\DashboardFunLineChartStats;
use Dashed\DashedEcommerceCore\Filament\Pages\Settings\DefaultEcommerceSettingsPage;
use Dashed\DashedEcommerceCore\Filament\Pages\Statistics\ProductGroupStatisticsPage;
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
                ExportFinancialReportPage::class,
                RevenueStatisticsPage::class,
                ProductStatisticsPage::class,
                DiscountStatisticsPage::class,
                POSPageRedirect::class,
                POSSettingsPage::class,
                POSCustomerPageRedirect::class,
                ProductGroupStatisticsPage::class,
                ActionsStatisticsPage::class,
                DefaultEcommerceSettingsPage::class,
                OrderCancelSettingsPage::class,
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
            ])
            ->resources([
                PaymentMethodResource::class,
                OrderLogTemplateResource::class,
                ShippingClassResource::class,
                ShippingZoneResource::class,
                ShippingMethodResource::class,
                DiscountCodeResource::class,
                ProductFaqResource::class,
                ProductResource::class,
                ProductCategoryResource::class,
                ProductFilterResource::class,
                ProductFilterOptionResource::class,
                ProductCharacteristicResource::class,
                OrderResource::class,
                ProductTabResource::class,
                ProductExtraResource::class,
                ProductGroupResource::class,
                PricePerUserResource::class,
                FulfillmentCompanyResource::class,
                GiftcardResource::class,
            ]);
    }

    public function boot(Panel $panel): void
    {

    }
}
