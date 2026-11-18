<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        // Belgische straten en gemeentes voor het adres aanvullen in de checkout en
        // de kassa, gevuld door dashed:import-belgian-addresses (BOSA open data).
        // Zie Services\Address\BelgianAddresses.
        if (! Schema::hasTable('dashed__belgian_streets')) {
            Schema::create('dashed__belgian_streets', function (Blueprint $table) {
                $table->id();
                $table->char('postcode', 4);
                $table->char('language', 2);
                $table->string('street', 150);
                $table->string('city', 100);
                $table->unique(['postcode', 'language', 'street']);
                $table->index(['postcode', 'street']);
            });
        }

        if (! Schema::hasTable('dashed__belgian_postcodes')) {
            Schema::create('dashed__belgian_postcodes', function (Blueprint $table) {
                $table->char('postcode', 4);
                $table->char('language', 2);
                $table->string('municipality', 100);
                $table->primary(['postcode', 'language']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('dashed__belgian_streets');
        Schema::dropIfExists('dashed__belgian_postcodes');
    }
};
