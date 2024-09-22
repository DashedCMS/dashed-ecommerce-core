<?php

namespace Dashed\DashedEcommerceCore;

use Dashed\DashedEcommerceCore\Livewire\PointOfSale\POSPage;
use Livewire\Livewire;
use Dashed\DashedCore\Models\User;
use Spatie\LaravelPackageTools\Package;
use Illuminate\Console\Scheduling\Schedule;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Commands\SendInvoices;
use Dashed\DashedEcommerceCore\Models\ProductCategory;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Dashed\DashedEcommerceCore\Commands\CancelOldOrders;
use Dashed\DashedEcommerceCore\Livewire\Frontend\Cart\Cart;
use Dashed\DashedEcommerceCore\Livewire\Orders\CancelOrder;
use Dashed\DashedEcommerceCore\Livewire\Orders\CreateOrderLog;
use Dashed\DashedEcommerceCore\Livewire\Frontend\Account\Orders;
use Dashed\DashedEcommerceCore\Livewire\Frontend\Cart\AddToCart;
use Dashed\DashedEcommerceCore\Livewire\Frontend\Cart\CartCount;
use Dashed\DashedEcommerceCore\Livewire\Orders\AddPaymentToOrder;
use Dashed\DashedEcommerceCore\Commands\UpdateProductInformations;
use Dashed\DashedEcommerceCore\Livewire\Frontend\Orders\ViewOrder;
use Dashed\DashedEcommerceCore\Livewire\Orders\Infolists\LogsList;
use Dashed\DashedEcommerceCore\Livewire\Frontend\Checkout\Checkout;
use Dashed\DashedEcommerceCore\Commands\RecalculatePurchasesCommand;
use Dashed\DashedEcommerceCore\Livewire\Frontend\Products\Searchbar;
use Dashed\DashedEcommerceCore\Livewire\Frontend\Products\ShowProduct;
use Dashed\DashedEcommerceCore\Livewire\Orders\Infolists\PaymentsList;
use Dashed\DashedEcommerceCore\Middleware\EcommerceFrontendMiddleware;
use Dashed\DashedEcommerceCore\Filament\Pages\Settings\VATSettingsPage;
use Dashed\DashedEcommerceCore\Filament\Resources\ShippingZoneResource;
use Dashed\DashedEcommerceCore\Livewire\Frontend\Products\ShowProducts;
use Dashed\DashedEcommerceCore\Livewire\Orders\ChangeOrderRetourStatus;
use Dashed\DashedEcommerceCore\Livewire\Orders\Infolists\ViewStatusses;
use Dashed\DashedEcommerceCore\Filament\Resources\PaymentMethodResource;
use Dashed\DashedEcommerceCore\Filament\Resources\ShippingClassResource;
use Dashed\DashedEcommerceCore\Filament\Widgets\Statistics\ProductCards;
use Dashed\DashedEcommerceCore\Filament\Widgets\Statistics\ProductChart;
use Dashed\DashedEcommerceCore\Filament\Widgets\Statistics\ProductTable;
use Dashed\DashedEcommerceCore\Filament\Widgets\Statistics\RevenueCards;
use Dashed\DashedEcommerceCore\Filament\Widgets\Statistics\RevenueChart;
use Dashed\DashedEcommerceCore\Filament\Pages\Settings\OrderSettingsPage;
use Dashed\DashedEcommerceCore\Filament\Resources\ShippingMethodResource;
use Dashed\DashedEcommerceCore\Filament\Widgets\Statistics\DiscountCards;
use Dashed\DashedEcommerceCore\Filament\Widgets\Statistics\DiscountChart;
use Dashed\DashedEcommerceCore\Filament\Widgets\Statistics\DiscountTable;
use Dashed\DashedEcommerceCore\Filament\Pages\Settings\InvoiceSettingsPage;
use Dashed\DashedEcommerceCore\Filament\Pages\Settings\ProductSettingsPage;
use Dashed\DashedEcommerceCore\Livewire\Frontend\Categories\ShowCategories;
use Dashed\DashedEcommerceCore\Livewire\Orders\Infolists\OrderProductsList;
use Dashed\DashedEcommerceCore\Filament\Pages\Settings\CheckoutSettingsPage;
use Dashed\DashedEcommerceCore\Livewire\Orders\ChangeOrderFulfillmentStatus;
use Dashed\DashedEcommerceCore\Livewire\Orders\SendOrderConfirmationToEmail;
use Dashed\DashedEcommerceCore\Livewire\Orders\Infolists\PaymentInformationList;
use Dashed\DashedEcommerceCore\Livewire\Orders\Infolists\ShippingInformationList;
use Dashed\DashedEcommerceCore\Livewire\Orders\Infolists\CustomerInformationBlockList;
use Dashed\DashedEcommerceCore\Commands\CheckPastDuePreorderDatesForProductsWithoutStockCommand;

class DashedEcommerceCoreServiceProvider extends PackageServiceProvider
{
    public static string $name = 'dashed-ecommerce-core';

    public function bootingPackage()
    {
        $this->app->booted(function () {
            $schedule = app(Schedule::class);
            $schedule->command(CheckPastDuePreorderDatesForProductsWithoutStockCommand::class)
                ->daily();
            $schedule->command(CancelOldOrders::class)
                ->everyFifteenMinutes();
            $schedule->command(UpdateProductInformations::class)
                ->twiceDaily()
                ->withoutOverlapping();
        });

        //Stats components
        Livewire::component('revenue-chart', RevenueChart::class);
        Livewire::component('revenue-cards', RevenueCards::class);
        Livewire::component('product-chart', ProductChart::class);
        Livewire::component('product-cards', ProductCards::class);
        Livewire::component('product-table', ProductTable::class);
        Livewire::component('discount-chart', DiscountChart::class);
        Livewire::component('discount-cards', DiscountCards::class);
        Livewire::component('discount-table', DiscountTable::class);

        //Backend components
        Livewire::component('change-order-fulfillment-status', ChangeOrderFulfillmentStatus::class);
        Livewire::component('change-order-retour-status', ChangeOrderRetourStatus::class);
        Livewire::component('add-payment-to-order', AddPaymentToOrder::class);
        Livewire::component('cancel-order', CancelOrder::class);
        Livewire::component('send-order-confirmation-to-email', SendOrderConfirmationToEmail::class);
        Livewire::component('create-order-log', CreateOrderLog::class);
        Livewire::component('order-shipping-information-list', ShippingInformationList::class);
        Livewire::component('order-payment-information-list', PaymentInformationList::class);
        Livewire::component('order-order-products-list', OrderProductsList::class);
        Livewire::component('order-payments-list', PaymentsList::class);
        Livewire::component('order-logs-list', LogsList::class);
        Livewire::component('order-customer-information-block-list', CustomerInformationBlockList::class);
        Livewire::component('order-view-statusses', ViewStatusses::class);

        //Frontend components
        Livewire::component('cart.cart', Cart::class);
        Livewire::component('cart.cart-count', CartCount::class);
        Livewire::component('cart.add-to-cart', AddToCart::class);
        Livewire::component('checkout.checkout', Checkout::class);
        Livewire::component('categories.show-categories', ShowCategories::class);
        Livewire::component('products.show-products', ShowProducts::class);
        Livewire::component('products.show-product', ShowProduct::class);
        Livewire::component('products.searchbar', Searchbar::class);
        Livewire::component('account.orders', Orders::class);
        Livewire::component('orders.view-order', ViewOrder::class);

        //POS components
        Livewire::component('point-of-sale', POSPage::class);

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
            __DIR__ . '/../resources/views/emails' => resource_path('views/vendor/dashed-ecommerce-core/emails'),
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
                    'icon' => 'document-check',
                    'page' => InvoiceSettingsPage::class,
                ],
                'order' => [
                    'name' => 'Bestellingen',
                    'description' => 'Instellingen voor de bestellingen',
                    'icon' => 'banknotes',
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
                    'icon' => 'receipt-percent',
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
                SendInvoices::class,
                UpdateProductInformations::class,
            ]);
    }
}
