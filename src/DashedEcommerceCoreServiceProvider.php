<?php

namespace Dashed\DashedEcommerceCore;

use Livewire\Livewire;
use Illuminate\Support\Facades\Gate;
use Dashed\DashedCore\Models\User;
use App\Providers\AppServiceProvider;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Dashed\DashedCore\Classes\Locales;
use Spatie\LaravelPackageTools\Package;
use Filament\Forms\Components\TextInput;
use Illuminate\Console\Scheduling\Schedule;
use Dashed\DashedEcommerceCore\Models\Order;
use Filament\Forms\Components\Builder\Block;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Models\ProductGroup;
use Dashed\DashedEcommerceCore\Commands\MigrateToV3;
use Dashed\DashedEcommerceCore\Commands\SendInvoices;
use Dashed\DashedEcommerceCore\Commands\ClearOldCarts;
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
use Dashed\DashedEcommerceCore\Livewire\Frontend\Cart\AddedToCart;
use Dashed\DashedEcommerceCore\Livewire\Frontend\Orders\ViewOrder;
use Dashed\DashedEcommerceCore\Livewire\Orders\Infolists\LogsList;
use Dashed\DashedEcommerceCore\Livewire\Frontend\Checkout\Checkout;
use Dashed\DashedEcommerceCore\Livewire\Orders\CreateTrackAndTrace;
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
use Dashed\DashedEcommerceCore\Commands\UpdateExpiredGlobalDiscountCodes;
use Dashed\DashedEcommerceCore\Filament\Pages\Settings\OrderSettingsPage;
use Dashed\DashedEcommerceCore\Filament\Resources\ShippingMethodResource;
use Dashed\DashedEcommerceCore\Filament\Widgets\Statistics\DiscountCards;
use Dashed\DashedEcommerceCore\Filament\Widgets\Statistics\DiscountChart;
use Dashed\DashedEcommerceCore\Filament\Widgets\Statistics\DiscountTable;
use Dashed\DashedEcommerceCore\Filament\Pages\Settings\InvoiceSettingsPage;
use Dashed\DashedEcommerceCore\Filament\Pages\Settings\ProductSettingsPage;
use Dashed\DashedEcommerceCore\Filament\Resources\OrderLogTemplateResource;
use Dashed\DashedEcommerceCore\Livewire\Frontend\Categories\ShowCategories;
use Dashed\DashedEcommerceCore\Livewire\Orders\Infolists\OrderProductsList;
use Dashed\DashedEcommerceCore\Filament\Pages\Settings\CheckoutSettingsPage;
use Dashed\DashedEcommerceCore\Filament\Resources\AbandonedCartFlowResource;
use Dashed\DashedEcommerceCore\Filament\Resources\AbandonedCartFlowResource\RelationManagers\FlowStepsRelationManager;
use Dashed\DashedEcommerceCore\Livewire\Orders\ChangeOrderFulfillmentStatus;
use Dashed\DashedEcommerceCore\Livewire\Orders\SendOrderConfirmationToEmail;
use Dashed\DashedEcommerceCore\Filament\Widgets\Statistics\ProductGroupCards;
use Dashed\DashedEcommerceCore\Filament\Widgets\Statistics\ProductGroupChart;
use Dashed\DashedEcommerceCore\Filament\Widgets\Statistics\ProductGroupTable;
use Dashed\DashedEcommerceCore\Filament\Pages\Settings\OrderCancelSettingsPage;
use Dashed\DashedEcommerceCore\Livewire\Orders\SendOrderToFulfillmentCompanies;
use Dashed\DashedEcommerceCore\Livewire\Orders\Infolists\PaymentInformationList;
use Dashed\DashedEcommerceCore\Filament\Widgets\Statistics\ActionStatisticsCards;
use Dashed\DashedEcommerceCore\Filament\Widgets\Statistics\ActionStatisticsChart;
use Dashed\DashedEcommerceCore\Filament\Widgets\Statistics\ActionStatisticsTable;
use Dashed\DashedEcommerceCore\Livewire\Orders\Infolists\ShippingInformationList;
use Dashed\DashedEcommerceCore\Filament\Pages\Settings\DefaultEcommerceSettingsPage;
use Dashed\DashedEcommerceCore\Livewire\Orders\Infolists\CustomerInformationBlockList;
use Dashed\DashedEcommerceCore\Commands\SendAbandonedCartEmails;
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
                ->daily()
                ->withoutOverlapping();
            $schedule->command(UpdateExpiredGlobalDiscountCodes::class)
                ->everyFiveMinutes()
                ->withoutOverlapping();
            $schedule->command(ClearOldCarts::class)
                ->hourly()
                ->withoutOverlapping();
            $schedule->command(SendAbandonedCartEmails::class)
                ->everyFiveMinutes()
                ->withoutOverlapping();
        });

        //Stats components
        Livewire::component('revenue-chart', RevenueChart::class);
        Livewire::component('revenue-cards', RevenueCards::class);
        Livewire::component('product-chart', ProductChart::class);
        Livewire::component('product-cards', ProductCards::class);
        Livewire::component('product-table', ProductTable::class);
        Livewire::component('product-group-chart', ProductGroupChart::class);
        Livewire::component('product-group-cards', ProductGroupCards::class);
        Livewire::component('product-group-table', ProductGroupTable::class);
        Livewire::component('discount-chart', DiscountChart::class);
        Livewire::component('discount-cards', DiscountCards::class);
        Livewire::component('discount-table', DiscountTable::class);
        Livewire::component('action-statistics-chart', ActionStatisticsChart::class);
        Livewire::component('action-statistics-cards', ActionStatisticsCards::class);
        Livewire::component('action-statistics-table', ActionStatisticsTable::class);

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
        Livewire::component('order-create-track-and-trace', CreateTrackAndTrace::class);

        //Frontend components
        Livewire::component('cart.cart', Cart::class);
        Livewire::component('cart.cart-count', CartCount::class);
        Livewire::component('cart.add-to-cart', AddToCart::class);
        Livewire::component('cart.added-to-cart-popup', AddedToCart::class);
        Livewire::component('cart.cart-popup', CartPopup::class);
        Livewire::component('checkout.checkout', Checkout::class);
        Livewire::component('categories.show-categories', ShowCategories::class);
        Livewire::component('products.show-products', ShowProducts::class);
        Livewire::component('products.show-product', ShowProduct::class);
        Livewire::component('products.searchbar', Searchbar::class);
        Livewire::component('account.orders', Orders::class);
        Livewire::component('orders.view-order', ViewOrder::class);

        Livewire::component(
            'dashed.dashed-ecommerce-core.filament.resources.abandoned-cart-flow-resource.relation-managers.flow-steps-relation-manager',
            FlowStepsRelationManager::class,
        );

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

        User::addDynamicRelation('lastOrderFromAllOrders', function (User $model) {
            return $model->allOrders()->latest()->first();
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
            'all-products',
        ]);

        cms()->builder('plugins', [
            new DashedEcommerceCorePlugin(),
        ]);

        Gate::policy(\Dashed\DashedEcommerceCore\Models\Cart::class, \Dashed\DashedEcommerceCore\Policies\CartPolicy::class);
        Gate::policy(\Dashed\DashedEcommerceCore\Models\DiscountCode::class, \Dashed\DashedEcommerceCore\Policies\DiscountCodePolicy::class);
        Gate::policy(\Dashed\DashedEcommerceCore\Models\FulfillmentCompany::class, \Dashed\DashedEcommerceCore\Policies\FulfillmentCompanyPolicy::class);
        Gate::policy(\Dashed\DashedEcommerceCore\Models\OrderLogTemplate::class, \Dashed\DashedEcommerceCore\Policies\OrderLogTemplatePolicy::class);
        Gate::policy(\Dashed\DashedEcommerceCore\Models\Order::class, \Dashed\DashedEcommerceCore\Policies\OrderPolicy::class);
        Gate::policy(\Dashed\DashedEcommerceCore\Models\PaymentMethod::class, \Dashed\DashedEcommerceCore\Policies\PaymentMethodPolicy::class);
        Gate::policy(\Dashed\DashedEcommerceCore\Models\ProductCategory::class, \Dashed\DashedEcommerceCore\Policies\ProductCategoryPolicy::class);
        Gate::policy(\Dashed\DashedEcommerceCore\Models\ProductCharacteristics::class, \Dashed\DashedEcommerceCore\Policies\ProductCharacteristicsPolicy::class);
        Gate::policy(\Dashed\DashedEcommerceCore\Models\ProductExtra::class, \Dashed\DashedEcommerceCore\Policies\ProductExtraPolicy::class);
        Gate::policy(\Dashed\DashedEcommerceCore\Models\ProductFaq::class, \Dashed\DashedEcommerceCore\Policies\ProductFaqPolicy::class);
        Gate::policy(\Dashed\DashedEcommerceCore\Models\ProductFilterOption::class, \Dashed\DashedEcommerceCore\Policies\ProductFilterOptionPolicy::class);
        Gate::policy(\Dashed\DashedEcommerceCore\Models\ProductFilter::class, \Dashed\DashedEcommerceCore\Policies\ProductFilterPolicy::class);
        Gate::policy(\Dashed\DashedEcommerceCore\Models\ProductGroup::class, \Dashed\DashedEcommerceCore\Policies\ProductGroupPolicy::class);
        Gate::policy(\Dashed\DashedEcommerceCore\Models\Product::class, \Dashed\DashedEcommerceCore\Policies\ProductPolicy::class);
        Gate::policy(\Dashed\DashedEcommerceCore\Models\ProductTab::class, \Dashed\DashedEcommerceCore\Policies\ProductTabPolicy::class);
        Gate::policy(\Dashed\DashedEcommerceCore\Models\ShippingClass::class, \Dashed\DashedEcommerceCore\Policies\ShippingClassPolicy::class);
        Gate::policy(\Dashed\DashedEcommerceCore\Models\ShippingMethod::class, \Dashed\DashedEcommerceCore\Policies\ShippingMethodPolicy::class);
        Gate::policy(\Dashed\DashedEcommerceCore\Models\ShippingZone::class, \Dashed\DashedEcommerceCore\Policies\ShippingZonePolicy::class);

        cms()->registerRolePermissions('E-commerce', [
            'view_order' => 'Bestellingen bekijken',
            'edit_order' => 'Bestellingen bewerken',
            'delete_order' => 'Bestellingen verwijderen',
            'view_cart' => 'Winkelwagens bekijken',
            'edit_cart' => 'Winkelwagens bewerken',
            'delete_cart' => 'Winkelwagens verwijderen',
            'view_product' => 'Producten bekijken',
            'edit_product' => 'Producten bewerken',
            'delete_product' => 'Producten verwijderen',
            'view_product_category' => 'Productcategorieën bekijken',
            'edit_product_category' => 'Productcategorieën bewerken',
            'delete_product_category' => 'Productcategorieën verwijderen',
            'view_product_characteristics' => 'Productkenmerken bekijken',
            'edit_product_characteristics' => 'Productkenmerken bewerken',
            'delete_product_characteristics' => 'Productkenmerken verwijderen',
            'view_product_extra' => 'Productextras bekijken',
            'edit_product_extra' => 'Productextras bewerken',
            'delete_product_extra' => 'Productextras verwijderen',
            'view_product_faq' => 'Product FAQ bekijken',
            'edit_product_faq' => 'Product FAQ bewerken',
            'delete_product_faq' => 'Product FAQ verwijderen',
            'view_product_filter' => 'Productfilters bekijken',
            'edit_product_filter' => 'Productfilters bewerken',
            'delete_product_filter' => 'Productfilters verwijderen',
            'view_product_filter_option' => 'Productfilteropties bekijken',
            'edit_product_filter_option' => 'Productfilteropties bewerken',
            'delete_product_filter_option' => 'Productfilteropties verwijderen',
            'view_product_group' => 'Productgroepen bekijken',
            'edit_product_group' => 'Productgroepen bewerken',
            'delete_product_group' => 'Productgroepen verwijderen',
            'view_product_tab' => 'Producttabs bekijken',
            'edit_product_tab' => 'Producttabs bewerken',
            'delete_product_tab' => 'Producttabs verwijderen',
            'view_discount_code' => 'Kortingscodes bekijken',
            'edit_discount_code' => 'Kortingscodes bewerken',
            'delete_discount_code' => 'Kortingscodes verwijderen',
            'view_order_log_template' => 'Orderlog templates bekijken',
            'edit_order_log_template' => 'Orderlog templates bewerken',
            'delete_order_log_template' => 'Orderlog templates verwijderen',
            'view_pos' => 'Point of Sale bekijken',
        ]);

        cms()->registerRolePermissions('Verzending', [
            'view_fulfillment_company' => 'Fulfillment bedrijven bekijken',
            'edit_fulfillment_company' => 'Fulfillment bedrijven bewerken',
            'delete_fulfillment_company' => 'Fulfillment bedrijven verwijderen',
            'view_shipping_class' => 'Verzendklassen bekijken',
            'edit_shipping_class' => 'Verzendklassen bewerken',
            'delete_shipping_class' => 'Verzendklassen verwijderen',
            'view_shipping_method' => 'Verzendmethoden bekijken',
            'edit_shipping_method' => 'Verzendmethoden bewerken',
            'delete_shipping_method' => 'Verzendmethoden verwijderen',
            'view_shipping_zone' => 'Verzendzones bekijken',
            'edit_shipping_zone' => 'Verzendzones bewerken',
            'delete_shipping_zone' => 'Verzendzones verwijderen',
        ]);

        cms()->registerRolePermissions('Betalingen', [
            'view_payment_method' => 'Betaalmethoden bekijken',
            'edit_payment_method' => 'Betaalmethoden bewerken',
            'delete_payment_method' => 'Betaalmethoden verwijderen',
        ]);

        cms()->registerRolePermissions('Statistics', [
            'view_statistics' => 'Statistieken bekijken',
        ]);

        cms()->registerRolePermissions('Export', [
            'view_exports' => 'Exports bekijken',
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
            __DIR__ . '/../resources/templates' => resource_path('views/' . config('dashed-core.site_theme', 'dashed')),
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

        cms()->registerSettingsPage(DefaultEcommerceSettingsPage::class, 'Algemene Ecommerce', 'banknotes', 'Algemene Ecommerce instellingen');
        cms()->registerSettingsPage(InvoiceSettingsPage::class, 'Facturatie instellingen', 'document-check', 'Instellingen voor de facturatie');
        cms()->registerSettingsPage(OrderSettingsPage::class, 'Bestellingen', 'banknotes', 'Instellingen voor de bestellingen');
        cms()->registerSettingsPage(OrderLogTemplateResource::class, 'Bestel log templates', 'newspaper', 'Stel templates in voor bestel logs');
        cms()->registerSettingsPage(PaymentMethodResource::class, 'Betaalmethodes', 'credit-card', 'Stel handmatige betaalmethodes in');
        cms()->registerSettingsPage(VATSettingsPage::class, 'BTW instellingen', 'receipt-percent', 'Beheren hoe je winkel belastingen in rekening brengt');
        cms()->registerSettingsPage(OrderCancelSettingsPage::class, 'Annuleer bestelling instellingen', 'arrow-uturn-left', 'Beheer instellingen voor het annuleren van bestellingen');
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
                'dompdf',
            ])
            ->hasViews()
            ->hasCommands([
                CheckPastDuePreorderDatesForProductsWithoutStockCommand::class,
                RecalculatePurchasesCommand::class,
                CancelOldOrders::class,
                SendInvoices::class,
                UpdateProductInformations::class,
                MigrateToV3::class,
                UpdateExpiredGlobalDiscountCodes::class,
                ClearOldCarts::class,
                SendAbandonedCartEmails::class,
            ]);

    }

    public static function createDefaultPages(): void
    {
        if (! \Dashed\DashedCore\Models\Customsetting::get('product_overview_page_id')) {
            $page = new \Dashed\DashedPages\Models\Page();
            foreach (Locales::getActivatedLocalesFromSites() as $locale) {
                $page->setTranslation('name', $locale, 'Producten');
                $page->setTranslation('slug', $locale, 'producten');
                $page->setTranslation('content', $locale, [
                    [
                        'data' => [
                            'in_container' => true,
                            'top_margin' => true,
                            'bottom_margin' => true,
                        ],
                        'type' => 'all-products',
                    ],
                ]);
            }
            $page->save();

            \Dashed\DashedCore\Models\Customsetting::set('product_overview_page_id', $page->id);
        }

        if (! \Dashed\DashedCore\Models\Customsetting::get('orders_page_id')) {
            $page = new \Dashed\DashedPages\Models\Page();
            foreach (Locales::getActivatedLocalesFromSites() as $locale) {
                $page->setTranslation('name', $locale, 'Bestellingen');
                $page->setTranslation('slug', $locale, 'bestellingen');
                $page->setTranslation('content', $locale, [
                    [
                        'data' => [
                            'in_container' => true,
                            'top_margin' => true,
                            'bottom_margin' => true,
                        ],
                        'type' => 'orders-block',
                    ],
                ]);
            }
            $page->save();

            \Dashed\DashedCore\Models\Customsetting::set('orders_page_id', $page->id);

            $page->metadata()->create([
                'noindex' => true,
            ]);
        }

        if (! \Dashed\DashedCore\Models\Customsetting::get('order_page_id')) {
            $page = new \Dashed\DashedPages\Models\Page();
            foreach (Locales::getActivatedLocalesFromSites() as $locale) {
                $page->setTranslation('name', $locale, 'Bestelling');
                $page->setTranslation('slug', $locale, 'bestelling');
                $page->setTranslation('content', $locale, [
                    [
                        'data' => [
                            'in_container' => true,
                            'top_margin' => true,
                            'bottom_margin' => true,
                        ],
                        'type' => 'view-order-block',
                    ],
                ]);
            }
            $page->save();

            \Dashed\DashedCore\Models\Customsetting::set('order_page_id', $page->id);

            $page->metadata()->create([
                'noindex' => true,
            ]);
        }

        if (! \Dashed\DashedCore\Models\Customsetting::get('cart_page_id')) {
            $page = new \Dashed\DashedPages\Models\Page();
            foreach (Locales::getActivatedLocalesFromSites() as $locale) {
                $page->setTranslation('name', $locale, 'Winkelwagen');
                $page->setTranslation('slug', $locale, 'winkelwagen');
                $page->setTranslation('content', $locale, [
                    [
                        'data' => [
                            'in_container' => true,
                            'top_margin' => true,
                            'bottom_margin' => true,
                        ],
                        'type' => 'cart-block',
                    ],
                ]);
            }
            $page->save();

            \Dashed\DashedCore\Models\Customsetting::set('cart_page_id', $page->id);
        }

        if (! \Dashed\DashedCore\Models\Customsetting::get('checkout_page_id')) {
            $page = new \Dashed\DashedPages\Models\Page();
            foreach (Locales::getActivatedLocalesFromSites() as $locale) {
                $page->setTranslation('name', $locale, 'Afrekenen');
                $page->setTranslation('slug', $locale, 'afrekenen');
                $page->setTranslation('content', $locale, [
                    [
                        'data' => [
                            'in_container' => true,
                            'top_margin' => true,
                            'bottom_margin' => true,
                        ],
                        'type' => 'checkout-block',
                    ],
                ]);
            }
            $page->save();

            \Dashed\DashedCore\Models\Customsetting::set('checkout_page_id', $page->id);
        }
    }
}
