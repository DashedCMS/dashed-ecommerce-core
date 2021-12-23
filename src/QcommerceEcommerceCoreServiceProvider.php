<?php

namespace Qubiqx\QcommerceEcommerceCore;

use Filament\PluginServiceProvider;
use Illuminate\Console\Scheduling\Schedule;
use Qubiqx\QcommerceEcommerceCore\Filament\Pages\Settings\CheckoutSettingsPage;
use Qubiqx\QcommerceEcommerceCore\Filament\Pages\Settings\InvoiceSettingsPage;
use Qubiqx\QcommerceEcommerceCore\Filament\Pages\Settings\OrderSettingsPage;
use Qubiqx\QcommerceEcommerceCore\Filament\Pages\Settings\VATSettingsPage;
use Qubiqx\QcommerceEcommerceCore\Filament\Resources\PaymentMethodResource;
use Spatie\LaravelPackageTools\Package;

class QcommerceEcommerceCoreServiceProvider extends PluginServiceProvider
{
    public static string $name = 'qcommerce-core';

    public function bootingPackage()
    {
        $this->app->booted(function () {
            $schedule = app(Schedule::class);
//            $schedule->command(CreateSitemap::class)->daily();
        });
    }

    public function configurePackage(Package $package): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        cms()->builder('routeModels', [
//            'page' => [
//                'name' => 'Pagina',
//                'pluralName' => 'Pagina\'s',
//                'class' => Page::class,
//                'nameField' => 'name',
//                'routeHandler' => PageRouteHandler::class,
//            ],
        ]);

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
                'checkout' => [
                    'name' => 'Afreken instellingen',
                    'description' => 'Je online betaalprocess aanpassen',
                    'icon' => 'shopping-cart',
                    'page' => CheckoutSettingsPage::class,
                ],
            ])
        );

        $package
            ->name('qcommerce-ecommerce-core')
            ->hasConfigFile([
//                'filament',
//                'filament-spatie-laravel-translatable-plugin',
//                'filesystems',
//                'laravellocalization',
//                'media-library',
//                'qcommerce-core',
            ])
            ->hasRoutes([
//                'frontend',
            ])
            ->hasViews()
            ->hasAssets()
            ->hasCommands([
//                CreateSitemap::class,
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
            VATSettingsPage::class,
        ]);
    }

    protected function getResources(): array
    {
        return array_merge(parent::getResources(), [
            PaymentMethodResource::class,
        ]);
    }
}
