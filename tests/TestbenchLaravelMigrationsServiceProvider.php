<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Tests;

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Illuminate\Database\Schema\Blueprint;

/**
 * dashed-core's eigen migraties gaan uit van een reeds bestaande `users`-tabel
 * (ze bevatten alleen ALTER-migraties zoals extend_users_table), zoals in een
 * echte Laravel-app altijd het geval is. Testbench's eigen basis-migraties
 * (die `users`, `sessions`, `cache`, etc. aanmaken) worden niet automatisch
 * geladen.
 *
 * Daarnaast verwacht dashed-core een `filament_media_library`(_folders)-tabel
 * die normaal door het (privé, Satis-gehoste) ralphjsmit/laravel-filament-
 * media-library package wordt aangemaakt. Dat package hoort niet standalone
 * in deze test-omgeving geïnstalleerd te worden (extern/onbekend voor deze
 * package-tests), dus we stubben hier alleen de kolommen die dashed-core's
 * migraties nodig hebben om te kunnen booten.
 *
 * Dit moet via een ServiceProvider::boot() lopen (niet via TestCase's
 * defineDatabaseMigrations()-hook), omdat RefreshDatabase zijn eigen
 * migrate:fresh draait met een verse Migrator-instantie waar een pad dat
 * eenmalig via die hook is geregistreerd niet in meekomt.
 */
class TestbenchLaravelMigrationsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // De echte app (dashed-cms/app/Providers/AppServiceProvider.php)
        // registreert dit altijd in register(); dashed-core zelf levert geen
        // default. Zonder minstens 1 site crasht alles wat Sites::getFirstSite()
        // aanroept (o.a. DefaultReturnPage::createIfMissing() bij elke boot).
        if (! count(cms()->builder('sites'))) {
            cms()->builder('sites', [
                0 => [
                    'id' => 'default',
                    'name' => 'Test Site',
                    'locales' => ['nl', 'en'],
                ],
            ]);
        }
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(\Orchestra\Testbench\default_migration_path());

        // MigrationsStarted vuurt vlak vóórdat elke migrate(:fresh)-run zijn
        // migraties uitvoert, dus de stub-tabellen staan er telkens op tijd
        // (ook bij RefreshDatabase's eigen migrate:fresh na deze provider's
        // boot-fase).
        $this->app['events']->listen(\Illuminate\Database\Events\MigrationsStarted::class, function () {
            $this->ensureFilamentMediaLibraryStubTables();
        });
    }

    private function ensureFilamentMediaLibraryStubTables(): void
    {
        if (! Schema::hasTable('filament_media_library_folders')) {
            Schema::create('filament_media_library_folders', function (Blueprint $table) {
                $table->id();
                $table->foreignId('parent_id')->nullable();
                $table->string('name');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('filament_media_library')) {
            Schema::create('filament_media_library', function (Blueprint $table) {
                $table->id();
                $table->foreignId('uploaded_by_user_id')->nullable();
                $table->string('caption')->nullable();
                $table->string('alt_text')->nullable();
                $table->foreignId('folder_id')->nullable()->constrained('filament_media_library_folders');
                $table->timestamps();
                $table->json('conversions')->nullable();
                $table->json('conversion_urls')->nullable();
            });
        }
    }
}
