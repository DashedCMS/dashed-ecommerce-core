<?php

namespace Dashed\DashedEcommerceCore;

use Livewire\Livewire;
use Dashed\DashedCore\Models\User;
use App\Providers\AppServiceProvider;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Spatie\LaravelPackageTools\Package;
use Filament\Forms\Components\TextInput;
use Illuminate\Console\Scheduling\Schedule;
use Dashed\DashedEcommerceCore\Models\Order;
use Filament\Forms\Components\Builder\Block;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Models\ProductGroup;
use Dashed\DashedEcommerceCore\Commands\MigrateToV3;
use Dashed\DashedEcommerceCore\Commands\SendInvoices;
use Dashed\DashedEcommerceCore\Models\ProductCategory;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Dashed\DashedEcommerceCore\Commands\CancelOldOrders;
use Dashed\DashedEcommerceCore\Filament\Pages\POS\POSPage;
use Dashed\DashedEcommerceCore\Livewire\Frontend\Cart\Cart;
use Dashed\DashedEcommerceCore\Livewire\Orders\CancelOrder;
use Dashed\DashedEcommerceCore\Livewire\Orders\CreateOrderLog;
use Dashed\DashedEcommerceCore\Livewire\Frontend\Account\Orders;
use Dashed\DashedEcommerceCore\Livewire\Frontend\Cart\AddToCart;
use Dashed\DashedEcommerceCore\Livewire\Frontend\Cart\CartCount;
use Dashed\DashedEcommerceCore\Livewire\Frontend\Cart\CartPopup;
use Dashed\DashedEcommerceCore\Livewire\Orders\AddPaymentToOrder;
use Dashed\DashedEcommerceCore\Commands\UpdateProductInformations;
use Dashed\DashedEcommerceCore\Filament\Pages\POS\POSCustomerPage;
use Dashed\DashedEcommerceCore\Livewire\Frontend\Orders\ViewOrder;
use Dashed\DashedEcommerceCore\Livewire\Orders\Infolists\LogsList;
use Dashed\DashedEcommerceCore\Livewire\Frontend\Checkout\Checkout;
use Dashed\DashedEcommerceCore\Commands\RecalculatePurchasesCommand;
use Dashed\DashedEcommerceCore\Livewire\Frontend\Products\Searchbar;
use Dashed\DashedEcommerceCore\Livewire\Frontend\Products\ShowProduct;
use Dashed\DashedEcommerceCore\Livewire\Orders\Infolists\PaymentsList;
use Dashed\DashedEcommerceCore\Middleware\EcommerceFrontendMiddleware;
use Dashed\DashedEcommerceCore\Filament\Pages\Settings\POSSettingsPage;
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
use Dashed\DashedEcommerceCore\Livewire\Orders\SendOrderToFulfillmentCompanies;
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
        Livewire::component('send-order-to-fulfillment-companies', SendOrderToFulfillmentCompanies::class);
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
        Livewire::component('cart.cart-popup', CartPopup::class);
        Livewire::component('checkout.checkout', Checkout::class);
        Livewire::component('categories.show-categories', ShowCategories::class);
        Livewire::component('products.show-products', ShowProducts::class);
        Livewire::component('products.show-product', ShowProduct::class);
        Livewire::component('products.searchbar', Searchbar::class);
        Livewire::component('account.orders', Orders::class);
        Livewire::component('orders.view-order', ViewOrder::class);

        //POS components
        Livewire::component('point-of-sale', POSPage::class);
        Livewire::component('customer-point-of-sale', POSCustomerPage::class);

        User::addDynamicRelation('orders', function (User $model) {
            return $model->hasMany(Order::class)
                ->whereIn('status', ['paid', 'waiting_for_confirmation', 'partially_paid'])
                ->orderBy('created_at', 'DESC');
        });

        User::addDynamicRelation('allOrders', function (User $model) {
            return $model->hasMany(Order::class)
                ->orderBy('created_at', 'DESC');
        });

        User::addDynamicRelation('lastOrder', function (User $model) {
            return $model->orders()->latest()->first();
        });

        //        $builderBlockClasses = [];
        //        if (config('dashed-ecommerce-core.registerDefaultBuilderBlocks', true)) {
        //            $builderBlockClasses[] = 'builderBlocks';
        //        }

        $builderBlockClasses[] = 'defaultPageBuilderBlocks';

        cms()->builder('builderBlockClasses', [
            self::class => $builderBlockClasses,
        ]);

        cms()->builder('createDefaultPages', [
            self::class => 'createDefaultPages',
        ]);

        cms()->builder('publishOnUpdate', [
            'dashed-ecommerce-core-config',
            'dashed-ecommerce-core-assets',
        ]);


        cms()->builder('blockDisabledForCache', [
            'orders-block',
            'cart-block',
            'checkout-block',
            'view-order-block',
        ]);

        cms()->builder('plugins', [
            new DashedEcommerceCorePlugin(),
        ]);
    }

    public static function builderBlocks()
    {
        $defaultBlocks = [
            Block::make('all-products')
                ->label('Alle producten')
                ->schema([]),
            Block::make('few-products')
                ->label('Paar producten')
                ->schema([
                    AppServiceProvider::getDefaultBlockFields(),
                    TextInput::make('title')
                        ->label('Titel'),
                    TextInput::make('subtitle')
                        ->label('Subtitel'),
                    TextInput::make('amount_of_products')
                        ->label('Aantal producten')
                        ->integer()
                        ->required()
                        ->default(4)
                        ->minValue(1)
                        ->maxValue(100),
                    Toggle::make('useCartRelatedItems')
                        ->label('Gebruik gerelateerde producten uit winkelwagen om de lijst aan te vullen'),
                    Select::make('products')
                        ->label('Producten')
                        ->helperText('Leeg laten om automatisch aan te vullen, indien je iets invult, worden alleen de ingevulde getoond')
                        ->multiple()
                        ->searchable()
                        ->preload()
                        ->options(function () {
                            $options = [];

                            foreach (Product::publicShowable()->get() as $product) {
                                $options[$product->id] = $product->nameWithParents;
                            }

                            return $options;
                        }),
                ]),
            Block::make('categories')
                ->label('Categorieeen')
                ->schema([
                    AppServiceProvider::getDefaultBlockFields(),
                    TextInput::make('title')
                        ->label('Titel')
                        ->required(),
                    Select::make('categories')
                        ->label('Categorieën')
                        ->searchable()
                        ->preload()
                        ->multiple()
                        ->options(function () {
                            return ProductCategory::all()->mapWithKeys(function ($category) {
                                return [$category->id => $category->nameWithParents];
                            });
                        }),
                ]),
        ];

        cms()
            ->builder('blocks', $defaultBlocks);
    }

    public static function defaultPageBuilderBlocks()
    {
        $defaultBlocks = [
            Block::make('orders-block')
                ->label('Bestellingen')
                ->schema([]),
            Block::make('cart-block')
                ->label('Winkelwagen')
                ->schema([]),
            Block::make('checkout-block')
                ->label('Checkout')
                ->schema([]),
            Block::make('view-order-block')
                ->label('Bestelling')
                ->schema([]),
        ];

        cms()
            ->builder('blocks', $defaultBlocks);
    }

    public function configurePackage(Package $package): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        //        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'dashed-ecommerce-core');

        //        $this->publishes([
        //            __DIR__ . '/../resources/views/frontend' => resource_path('views/vendor/dashed-ecommerce-core/frontend'),
        //            __DIR__ . '/../resources/views/emails' => resource_path('views/vendor/dashed-ecommerce-core/emails'),
        //        ], 'dashed-ecommerce-core-views');

        $this->publishes([
            __DIR__ . '/../resources/templates' => resource_path('views/' . env('SITE_THEME', 'dashed')),
            __DIR__ . '/../resources/component-templates' => resource_path('views/components'),
        ], 'dashed-templates');

        cms()->builder(
            'frontendMiddlewares',
            [
                EcommerceFrontendMiddleware::class,
            ]
        );

        cms()->registerRouteModel(Product::class, 'Product');
        cms()->registerRouteModel(ProductGroup::class, 'Product groep');
        cms()->registerRouteModel(ProductCategory::class, 'Product categorie');

        cms()->registerSettingsPage(InvoiceSettingsPage::class, 'Facturatie instellingen', 'document-check', 'Instellingen voor de facturatie');
        cms()->registerSettingsPage(OrderSettingsPage::class, 'Bestellingen', 'banknotes', 'Instellingen voor de bestellingen');
        cms()->registerSettingsPage(PaymentMethodResource::class, 'Betaalmethodes', 'credit-card', 'Stel handmatige betaalmethodes in');
        cms()->registerSettingsPage(VATSettingsPage::class, 'BTW instellingen', 'receipt-percent', 'Beheren hoe je winkel belastingen in rekening brengt');
        cms()->registerSettingsPage(ProductSettingsPage::class, 'Product instellingen', 'shopping-bag', 'Beheren instellingen over je producten');
        cms()->registerSettingsPage(CheckoutSettingsPage::class, 'Afreken instellingen', 'shopping-cart', 'Je online betaalprocess aanpassen');
        cms()->registerSettingsPage(ShippingClassResource::class, 'Verzendklasses', 'truck', 'Is een product breekbaar of veel groter? Reken een meerprijs');
        cms()->registerSettingsPage(ShippingZoneResource::class, 'Verzendzones', 'truck', 'Bepaal waar je allemaal naartoe verstuurd');
        cms()->registerSettingsPage(ShippingMethodResource::class, 'Verzendmethodes', 'truck', 'Maak verzendmethodes aan');
        cms()->registerSettingsPage(POSSettingsPage::class, 'Point of Sale', 'banknotes', 'Bewerk je POS');

        $package
            ->name('dashed-ecommerce-core')
            ->hasRoutes([
                'frontend',
                'point-of-sale',
            ])
            ->hasConfigFile([
                'dashed-ecommerce-core',
            ])
            ->hasViews()
            ->hasCommands([
                CheckPastDuePreorderDatesForProductsWithoutStockCommand::class,
                RecalculatePurchasesCommand::class,
                CancelOldOrders::class,
                SendInvoices::class,
                UpdateProductInformations::class,
                MigrateToV3::class,
            ]);
    }

    public static function createDefaultPages(): void
    {
        if (! \Dashed\DashedCore\Models\Customsetting::get('product_overview_page_id')) {
            $page = new \Dashed\DashedPages\Models\Page();
            $page->setTranslation('name', 'nl', 'Producten');
            $page->setTranslation('slug', 'nl', 'producten');
            $page->setTranslation('content', 'nl', [
                [
                    'data' => [],
                    'type' => 'all-products',
                ],
            ]);
            $page->save();

            \Dashed\DashedCore\Models\Customsetting::set('product_overview_page_id', $page->id);
        }

        if (! \Dashed\DashedCore\Models\Customsetting::get('orders_page_id')) {
            $page = new \Dashed\DashedPages\Models\Page();
            $page->setTranslation('name', 'nl', 'Bestellingen');
            $page->setTranslation('slug', 'nl', 'bestellingen');
            $page->setTranslation('content', 'nl', [
                [
                    'data' => [],
                    'type' => 'orders-block',
                ],
            ]);
            $page->save();

            \Dashed\DashedCore\Models\Customsetting::set('orders_page_id', $page->id);
        }

        if (! \Dashed\DashedCore\Models\Customsetting::get('order_page_id')) {
            $page = new \Dashed\DashedPages\Models\Page();
            $page->setTranslation('name', 'nl', 'Bestelling');
            $page->setTranslation('slug', 'nl', 'bestelling');
            $page->setTranslation('content', 'nl', [
                [
                    'data' => [],
                    'type' => 'view-order-block',
                ],
            ]);
            $page->save();

            \Dashed\DashedCore\Models\Customsetting::set('order_page_id', $page->id);
        }

        if (! \Dashed\DashedCore\Models\Customsetting::get('cart_page_id')) {
            $page = new \Dashed\DashedPages\Models\Page();
            $page->setTranslation('name', 'nl', 'Winkelwagen');
            $page->setTranslation('slug', 'nl', 'winkelwagen');
            $page->setTranslation('content', 'nl', [
                [
                    'data' => [],
                    'type' => 'cart-block',
                ],
            ]);
            $page->save();

            \Dashed\DashedCore\Models\Customsetting::set('cart_page_id', $page->id);
        }

        if (! \Dashed\DashedCore\Models\Customsetting::get('checkout_page_id')) {
            $page = new \Dashed\DashedPages\Models\Page();
            $page->setTranslation('name', 'nl', 'Afrekenen');
            $page->setTranslation('slug', 'nl', 'afrekenen');
            $page->setTranslation('content', 'nl', [
                [
                    'data' => [],
                    'type' => 'checkout-block',
                ],
            ]);
            $page->save();

            \Dashed\DashedCore\Models\Customsetting::set('checkout_page_id', $page->id);
        }
    }
}
