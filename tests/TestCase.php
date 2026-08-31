<?php

namespace Dashed\DashedEcommerceCore\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Dashed\DashedCore\DashedCoreServiceProvider;
use Dashed\DashedPages\DashedPagesServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Database\Eloquent\Factories\Factory;
use Dashed\DashedEcommerceCore\DashedEcommerceCoreServiceProvider;

class TestCase extends Orchestra
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'Dashed\\DashedEcommerceCore\\Database\\Factories\\'.class_basename($modelName).'Factory'
        );
    }

    protected function getPackageProviders($app)
    {
        $providers = [
            // Levert Laravel's basis-migraties (users, cache, jobs) plus de
            // stub-tabellen voor het privé media-library-package. dashed-core
            // heeft alleen ALTER-migraties op `users`, dus zonder deze provider
            // faalt elke test al op migrate:fresh.
            TestbenchLaravelMigrationsServiceProvider::class,
            DashedCoreServiceProvider::class,
            DashedPagesServiceProvider::class,
            DashedEcommerceCoreServiceProvider::class,
        ];

        // LaravelLocalization levert de `laravellocalization`-binding die de
        // frontend-routes bij boot nodig hebben. Conditioneel, zodat de test-
        // suite ook draait wanneer het package (nog) niet aanwezig is.
        if (class_exists(\Dashed\LaravelLocalization\LaravelLocalizationServiceProvider::class)) {
            array_unshift($providers, \Dashed\LaravelLocalization\LaravelLocalizationServiceProvider::class);
        }

        // De mobile-api levert de route-middleware (mobile.site / ability) waar
        // de product-write-endpoints achter zitten. Alleen registreren als het
        // package aanwezig is, zodat de overige tests onafhankelijk blijven.
        if (class_exists(\Dashed\DashedMobileApi\DashedMobileApiServiceProvider::class)) {
            $providers[] = \Dashed\DashedMobileApi\DashedMobileApiServiceProvider::class;
        }

        // Sanctum levert de 'sanctum'-guard waarmee de MobileApi-tests
        // authenticeren (actingAs(..., 'sanctum')).
        if (class_exists(\Laravel\Sanctum\SanctumServiceProvider::class)) {
            $providers[] = \Laravel\Sanctum\SanctumServiceProvider::class;
        }

        return $providers;
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('database.default', 'testing');

        // De sanctum-guard bestaat alleen in een echte app-config; hier nodig
        // voor de MobileApi-routes (auth:sanctum) en actingAs(..., 'sanctum').
        config()->set('auth.guards.sanctum', ['driver' => 'sanctum', 'provider' => 'users']);

        // De 'dashed'-disk is in een echte app een DigitalOcean Spaces-bucket
        // (config/filesystems.php van de app zelf). De Testbench-skeleton kent
        // hem niet, terwijl o.a. Order::createInvoice() erop schrijft; lokaal
        // in de test-storage is voor deze suite genoeg.
        config()->set('filesystems.disks.dashed', [
            'driver' => 'local',
            'root' => storage_path('app/dashed'),
            'throw' => false,
        ]);

        // Standaard draait de suite op Testbench's in-memory sqlite. Een deel
        // van de migraties van dit package kan sqlite niet uitvoeren
        // (dropForeign op naam, enum-kolommen wijzigen), dus met
        // DB_TEST_CONNECTION=mysql draait dezelfde suite op MySQL: dezelfde
        // engine als productie.
        if (env('DB_TEST_CONNECTION') === 'mysql') {
            config()->set('database.connections.testing', [
                'driver' => 'mysql',
                'host' => env('DB_TEST_HOST', '127.0.0.1'),
                'port' => env('DB_TEST_PORT', '3306'),
                'database' => env('DB_TEST_DATABASE', 'dashed_ec_core_test'),
                'username' => env('DB_TEST_USERNAME', 'root'),
                'password' => env('DB_TEST_PASSWORD', ''),
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
                'prefix' => '',
                'strict' => false,
                'engine' => null,
            ]);
        }
    }
}
