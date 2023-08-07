<?php

namespace Dashed\DashedEcommerceCore;

use Livewire\Livewire;
use Dashed\DashedCore\Models\User;
use Filament\PluginServiceProvider;
use Spatie\LaravelPackageTools\Package;
use Illuminate\Console\Scheduling\Schedule;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Models\ProductCategory;
use Dashed\DashedEcommerceCore\Commands\CancelOldOrders;
use Dashed\DashedEcommerceCore\Livewire\Frontend\Cart\Cart;
use Dashed\DashedEcommerceCore\Livewire\Orders\CreateOrderLog;
use Dashed\DashedEcommerceCore\Filament\Resources\OrderResource;
use Dashed\DashedEcommerceCore\Livewire\Frontend\Account\Orders;
use Dashed\DashedEcommerceCore\Livewire\Frontend\Cart\AddToCart;
use Dashed\DashedEcommerceCore\Livewire\Frontend\Cart\CartCount;
use Dashed\DashedEcommerceCore\Livewire\Orders\AddPaymentToOrder;
use Dashed\DashedEcommerceCore\Filament\Resources\ProductResource;
use Dashed\DashedEcommerceCore\Livewire\Frontend\Orders\ViewOrder;
use Dashed\DashedEcommerceCore\Livewire\Frontend\Checkout\Checkout;
use Dashed\DashedEcommerceCore\Commands\RecalculatePurchasesCommand;
use Dashed\DashedEcommerceCore\Livewire\Frontend\Products\ShowProduct;
use Dashed\DashedEcommerceCore\Middleware\EcommerceFrontendMiddleware;
use Dashed\DashedEcommerceCore\Filament\Pages\Exports\ExportOrdersPage;
use Dashed\DashedEcommerceCore\Filament\Pages\Settings\VATSettingsPage;
use Dashed\DashedEcommerceCore\Filament\Resources\DiscountCodeResource;
use Dashed\DashedEcommerceCore\Filament\Resources\ShippingZoneResource;
use Dashed\DashedEcommerceCore\Livewire\Frontend\Products\ShowProducts;
use Dashed\DashedEcommerceCore\Livewire\Orders\ChangeOrderRetourStatus;
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
use Dashed\DashedEcommerceCore\Livewire\Frontend\Categories\ShowCategories;
use Dashed\DashedEcommerceCore\Filament\Pages\Settings\CheckoutSettingsPage;
use Dashed\DashedEcommerceCore\Filament\Widgets\Revenue\AlltimeRevenueStats;
use Dashed\DashedEcommerceCore\Filament\Widgets\Revenue\MonthlyRevenueStats;
use Dashed\DashedEcommerceCore\Livewire\Orders\ChangeOrderFulfillmentStatus;
use Dashed\DashedEcommerceCore\Livewire\Orders\SendOrderConfirmationToEmail;
use Dashed\DashedEcommerceCore\Filament\Resources\ProductFilterOptionResource;
use Dashed\DashedEcommerceCore\Filament\Pages\Statistics\ProductStatisticsPage;
use Dashed\DashedEcommerceCore\Filament\Pages\Statistics\RevenueStatisticsPage;
use Dashed\DashedEcommerceCore\Filament\Pages\Statistics\DiscountStatisticsPage;
use Dashed\DashedEcommerceCore\Filament\Resources\ProductCharacteristicResource;
use Dashed\DashedEcommerceCore\Filament\Widgets\Revenue\DashboardFunLineChartStats;
use Dashed\DashedEcommerceCore\Filament\Widgets\Revenue\PaymentMethodPieChartWidget;
use Dashed\DashedEcommerceCore\Filament\Widgets\Revenue\MonthlyRevenueAndReturnLineChartStats;
use Dashed\DashedEcommerceCore\Commands\CheckPastDuePreorderDatesForProductsWithoutStockCommand;

class DashedEcommerceCoreServiceProvider extends PluginServiceProvider
{
    public static string $name = 'dashed-ecommerce-core';

    public function bootingPackage()
    {
        $this->app->booted(function () {
            $schedule = app(Schedule::class);
            $schedule->command(CheckPastDuePreorderDatesForProductsWithoutStockCommand::class)->daily();
            //            $schedule->command(RecalculatePurchasesCommand::class)->weekly();
            $schedule->command(CancelOldOrders::class)->everyFifteenMinutes();
        });

        Livewire::component('change-order-fulfillment-status', ChangeOrderFulfillmentStatus::class);
        Livewire::component('change-order-retour-status', ChangeOrderRetourStatus::class);
        Livewire::component('add-payment-to-order', AddPaymentToOrder::class);
        Livewire::component('send-order-confirmation-to-email', SendOrderConfirmationToEmail::class);
        Livewire::component('create-order-log', CreateOrderLog::class);

        //Frontend components
        Livewire::component('cart.cart', Cart::class);
        Livewire::component('cart.cart-count', CartCount::class);
        Livewire::component('cart.add-to-cart', AddToCart::class);
        Livewire::component('checkout.checkout', Checkout::class);
        Livewire::component('categories.show-categories', ShowCategories::class);
        Livewire::component('products.show-products', ShowProducts::class);
        Livewire::component('products.show-product', ShowProduct::class);
        Livewire::component('account.orders', Orders::class);
        Livewire::component('orders.view-order', ViewOrder::class);

        User::addDynamicRelation('orders', function (User $model) {
            return $model->hasMany(Order::class)
                ->whereIn('status', ['paid', 'waiting_for_confirmation', 'partially_paid'])
                ->orderBy('created_at', 'DESC');
        });
        User::addDynamicRelation('lastOrder', function (User $model) {
            return $model->orders()->latest()->first();
        });
    }

    public function configurePackage(Package $package): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'dashed-ecommerce-core');
        $this->publishes([
            __DIR__ . '/../resources/views/frontend' => resource_path('views/vendor/dashed-ecommerce-core/frontend'),
        ], 'dashed-ecommerce-core-views');

        cms()->builder(
            'frontendMiddlewares',
            array_merge(cms()->builder('frontendMiddlewares'), [
                EcommerceFrontendMiddleware::class,
            ])
        );

        cms()->builder(
            'routeModels',
            array_merge(cms()->builder('routeModels'), [
                'product' => [
                    'name' => 'Product',
                    'pluralName' => 'Products',
                    'class' => Product::class,
                    'nameField' => 'name',
                ],
                'productCategory' => [
                    'name' => 'Product categorie',
                    'pluralName' => 'Product categorieën',
                    'class' => ProductCategory::class,
                    'nameField' => 'name',
                ],
            ])
        );

        cms()->builder(
            'settingPages',
            array_merge(cms()->builder('settingPages'), [
                'invoicing' => [
                    'name' => 'Facturatie instellingen',
                    'description' => 'Instellingen voor de facturatie',
                    'icon' => 'document-report',
                    'page' => InvoiceSettingsPage::class,
                ],
                'order' => [
                    'name' => 'Bestellingen',
                    'description' => 'Instellingen voor de bestellingen',
                    'icon' => 'cash',
                    'page' => OrderSettingsPage::class,
                ],
                'paymentMethods' => [
                    'name' => 'Betaalmethodes',
                    'description' => 'Stel handmatige betaalmethodes in',
                    'icon' => 'credit-card',
                    'page' => PaymentMethodResource::class,
                ],
                'vat' => [
                    'name' => 'BTW instellingen',
                    'description' => 'Beheren hoe je winkel belastingen in rekening brengt',
                    'icon' => 'receipt-tax',
                    'page' => VATSettingsPage::class,
                ],
                'product' => [
                    'name' => 'Product instellingen',
                    'description' => 'Beheren instellingen over je producten',
                    'icon' => 'shopping-bag',
                    'page' => ProductSettingsPage::class,
                ],
                'checkout' => [
                    'name' => 'Afreken instellingen',
                    'description' => 'Je online betaalprocess aanpassen',
                    'icon' => 'shopping-cart',
                    'page' => CheckoutSettingsPage::class,
                ],
                'shippingClass' => [
                    'name' => 'Verzendklasses',
                    'description' => 'Is een product breekbaar of veel groter? Reken een meerprijs',
                    'icon' => 'truck',
                    'page' => ShippingClassResource::class,
                ],
                'shippingZone' => [
                    'name' => 'Verzendzones',
                    'description' => 'Bepaal waar je allemaal naartoe verstuurd',
                    'icon' => 'truck',
                    'page' => ShippingZoneResource::class,
                ],
                'shippingMethod' => [
                    'name' => 'Verzendmethodes',
                    'description' => 'Maak verzendmethodes aan',
                    'icon' => 'truck',
                    'page' => ShippingMethodResource::class,
                ],
            ])
        );

        $package
            ->name('dashed-ecommerce-core')
            ->hasRoutes([
                'frontend',
            ])
            ->hasAssets()
            ->hasCommands([
                CheckPastDuePreorderDatesForProductsWithoutStockCommand::class,
                RecalculatePurchasesCommand::class,
                CancelOldOrders::class,
            ]);
    }

    protected function getStyles(): array
    {
        return array_merge(parent::getStyles(), [
            'dashed-ecommerce-core' => str_replace('/vendor/dashed/dashed-ecommerce-core/src', '', str_replace('/packages/dashed/dashed-ecommerce-core/src', '', __DIR__)) . '/vendor/dashed/dashed-ecommerce-core/resources/dist/css/dashed-ecommerce-core.css',
        ]);
    }

    protected function getPages(): array
    {
        return array_merge(parent::getPages(), [
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
        ]);
    }

    protected function getResources(): array
    {
        return array_merge(parent::getResources(), [
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

    protected function getWidgets(): array
    {
        return array_merge(parent::getWidgets(), [
            MonthlyRevenueAndReturnLineChartStats::class,
            DailyRevenueStats::class,
            MonthlyRevenueStats::class,
            YearlyRevenueStats::class,
            AlltimeRevenueStats::class,
            PaymentMethodPieChartWidget::class,
            DashboardFunLineChartStats::class,
        ]);
    }
}
