<?php

namespace Qubiqx\QcommerceEcommerceCore;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Qubiqx\QcommerceEcommerceCore\Commands\QcommerceEcommerceCoreCommand;

class QcommerceEcommerceCoreServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('qcommerce-ecommerce-core')
            ->hasConfigFile()
            ->hasViews()
            ->hasMigration('create_qcommerce-ecommerce-core_table')
            ->hasCommand(QcommerceEcommerceCoreCommand::class);
    }
}
