<?php

namespace Qubiqx\QcommerceEcommerceCore;

use Livewire\Livewire;
use Filament\PluginServiceProvider;
use Qubiqx\QcommerceCore\Models\User;
use Qubiqx\QcommerceEcommerceCore\Livewire\Frontend\Auth\ForgotPassword;
use Qubiqx\QcommerceEcommerceCore\Livewire\Frontend\Auth\ResetPassword;
use Spatie\LaravelPackageTools\Package;
use Illuminate\Console\Scheduling\Schedule;
use Qubiqx\QcommerceEcommerceCore\Models\Order;
use Qubiqx\QcommerceEcommerceCore\Models\Product;
use Qubiqx\QcommerceEcommerceCore\Models\ProductCategory;
use Qubiqx\QcommerceEcommerceCore\Commands\CancelOldOrders;
use Qubiqx\QcommerceEcommerceCore\Livewire\Frontend\Cart\Cart;
use Qubiqx\QcommerceEcommerceCore\Livewire\Frontend\Auth\Login;
use Qubiqx\QcommerceEcommerceCore\Livewire\Orders\CreateOrderLog;
use Qubiqx\QcommerceEcommerceCore\Livewire\Frontend\Cart\Checkout;
use Qubiqx\QcommerceEcommerceCore\Filament\Resources\OrderResource;
use Qubiqx\QcommerceEcommerceCore\Livewire\Frontend\Cart\AddToCart;
use Qubiqx\QcommerceEcommerceCore\Livewire\Frontend\Cart\CartCount;
use Qubiqx\QcommerceEcommerceCore\Livewire\Orders\AddPaymentToOrder;
use Qubiqx\QcommerceEcommerceCore\Filament\Resources\ProductResource;
use Qubiqx\QcommerceEcommerceCore\Commands\RecalculatePurchasesCommand;
use Qubiqx\QcommerceEcommerceCore\Livewire\Frontend\Products\ShowProduct;
use Qubiqx\QcommerceEcommerceCore\Middleware\EcommerceFrontendMiddleware;
use Qubiqx\QcommerceEcommerceCore\Filament\Pages\Exports\ExportOrdersPage;
use Qubiqx\QcommerceEcommerceCore\Filament\Pages\Settings\VATSettingsPage;
use Qubiqx\QcommerceEcommerceCore\Filament\Resources\DiscountCodeResource;
use Qubiqx\QcommerceEcommerceCore\Filament\Resources\ShippingZoneResource;
use Qubiqx\QcommerceEcommerceCore\Livewire\Frontend\Products\ShowProducts;
use Qubiqx\QcommerceEcommerceCore\Livewire\Orders\ChangeOrderRetourStatus;
use Qubiqx\QcommerceEcommerceCore\Filament\Resources\PaymentMethodResource;
use Qubiqx\QcommerceEcommerceCore\Filament\Resources\ProductFilterResource;
use Qubiqx\QcommerceEcommerceCore\Filament\Resources\ShippingClassResource;
use Qubiqx\QcommerceEcommerceCore\Filament\Pages\Exports\ExportInvoicesPage;
use Qubiqx\QcommerceEcommerceCore\Filament\Pages\Exports\ExportProductsPage;
use Qubiqx\QcommerceEcommerceCore\Filament\Pages\Settings\OrderSettingsPage;
use Qubiqx\QcommerceEcommerceCore\Filament\Resources\ShippingMethodResource;
use Qubiqx\QcommerceEcommerceCore\Filament\Resources\ProductCategoryResource;
use Qubiqx\QcommerceEcommerceCore\Filament\Widgets\Revenue\DailyRevenueStats;
use Qubiqx\QcommerceEcommerceCore\Filament\Pages\Settings\InvoiceSettingsPage;
use Qubiqx\QcommerceEcommerceCore\Filament\Pages\Settings\ProductSettingsPage;
use Qubiqx\QcommerceEcommerceCore\Filament\Widgets\Revenue\YearlyRevenueStats;
use Qubiqx\QcommerceEcommerceCore\Livewire\Frontend\Categories\ShowCategories;
use Qubiqx\QcommerceEcommerceCore\Filament\Pages\Settings\CheckoutSettingsPage;
use Qubiqx\QcommerceEcommerceCore\Filament\Widgets\Revenue\AlltimeRevenueStats;
use Qubiqx\QcommerceEcommerceCore\Filament\Widgets\Revenue\MonthlyRevenueStats;
use Qubiqx\QcommerceEcommerceCore\Livewire\Orders\ChangeOrderFulfillmentStatus;
use Qubiqx\QcommerceEcommerceCore\Livewire\Orders\SendOrderConfirmationToEmail;
use Qubiqx\QcommerceEcommerceCore\Filament\Resources\ProductFilterOptionResource;
use Qubiqx\QcommerceEcommerceCore\Filament\Pages\Statistics\ProductStatisticsPage;
use Qubiqx\QcommerceEcommerceCore\Filament\Pages\Statistics\RevenueStatisticsPage;
use Qubiqx\QcommerceEcommerceCore\Filament\Pages\Statistics\DiscountStatisticsPage;
use Qubiqx\QcommerceEcommerceCore\Filament\Resources\ProductCharacteristicResource;
use Qubiqx\QcommerceEcommerceCore\Filament\Widgets\Revenue\DashboardFunLineChartStats;
use Qubiqx\QcommerceEcommerceCore\Filament\Widgets\Revenue\PaymentMethodPieChartWidget;
use Qubiqx\QcommerceEcommerceCore\Filament\Widgets\Revenue\MonthlyRevenueAndReturnLineChartStats;
use Qubiqx\QcommerceEcommerceCore\Commands\CheckPastDuePreorderDatesForProductsWithoutStockCommand;

class QcommerceEcommerceCoreServiceProvider extends PluginServiceProvider
{
    public static string $name = 'qcommerce-ecommerce-core';

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
        Livewire::component('cart.checkout', Checkout::class);
        Livewire::component('cart.cart-count', CartCount::class);
        Livewire::component('cart.add-to-cart', AddToCart::class);
        Livewire::component('categories.show-categories', ShowCategories::class);
        Livewire::component('products.show-products', ShowProducts::class);
        Livewire::component('products.show-product', ShowProduct::class);
        Livewire::component('auth.login', Login::class);
        Livewire::component('auth.forgot-password', ForgotPassword::class);
        Livewire::component('auth.reset-password', ResetPassword::class);

        User::addDynamicRelation('orders', function (User $model) {
            return $model->hasMany(Order::class)->whereIn('status', ['paid', 'waiting_for_confirmation', 'partially_paid'])->orderBy('created_at', 'DESC');
        });
        User::addDynamicRelation('lastOrder', function (User $model) {
            return $model->orders()->latest()->first();
        });
    }

    public function configurePackage(Package $package): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'qcommerce-ecommerce-core');
        $this->publishes([
            __DIR__ . '/../resources/views/frontend' => resource_path('views/vendor/qcommerce-ecommerce-core/frontend'),
        ], 'qcommerce-ecommerce-core-views');

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
            ->name('qcommerce-ecommerce-core')
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
            'qcommerce-ecommerce-core' => str_replace('/vendor/qubiqx/qcommerce-ecommerce-core/src', '', str_replace('/packages/qubiqx/qcommerce-ecommerce-core/src', '', __DIR__)) . '/vendor/qubiqx/qcommerce-ecommerce-core/resources/dist/css/qcommerce-ecommerce-core.css',
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
