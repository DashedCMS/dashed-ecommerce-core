<?php

namespace Qubiqx\QcommerceEcommerceCore;

use Filament\PluginServiceProvider;
use Illuminate\Console\Scheduling\Schedule;
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

        cms()->builder('settingPages', [
//            'general' => [
//                'name' => 'Algemeen',
//                'description' => 'Algemene informatie van de website',
//                'icon' => 'cog',
//                'page' => GeneralSettingsPage::class,
//            ],
        ]);

        $package
            ->name('qcommerce-core')
            ->hasConfigFile([
                'filament',
                'filament-spatie-laravel-translatable-plugin',
                'filesystems',
                'laravellocalization',
                'media-library',
                'qcommerce-core',
            ])
            ->hasRoutes([
                'frontend',
            ])
            ->hasViews()
            ->hasAssets()
            ->hasCommands([
//                CreateSitemap::class,
            ]);
    }

    protected function getStyles(): array
    {
        return [
            'qcommerce-ecommerce-core' => str_replace('/vendor/qubiqx/qcommerce-ecommerce-core/src', '', str_replace('/packages/qubiqx/qcommerce-ecommerce-core/src', '', __DIR__)) . '/vendor/qubiqx/qcommerce-ecommerce-core/resources/dist/css/qcommerce-ecommerce-core.css',
        ];
    }

    protected function getPages(): array
    {
        return array_merge(parent::getPages(), [
//            SettingsPage::class,
        ]);
    }

    protected function getResources(): array
    {
        return [
//            PageResource::class,
        ];
    }
}
