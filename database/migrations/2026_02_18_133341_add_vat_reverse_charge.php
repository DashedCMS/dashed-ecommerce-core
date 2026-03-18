<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('dashed__shipping_zones', function (Blueprint $table) {
            $table->boolean('vat_reverse_charge')
                ->default(false);
            $table->boolean('country_specific_vat')
                ->default(false);
            $table->integer('country_specific_vat_rate')
                ->nullable();
        });

        Schema::table('dashed__orders', function (Blueprint $table) {
            $table->boolean('vat_reverse_charge')
                ->default(false);
        });
    }

    public function down(): void
    {
    }
};
