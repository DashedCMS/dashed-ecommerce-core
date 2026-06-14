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

        return $providers;
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('database.default', 'testing');
    }
}
