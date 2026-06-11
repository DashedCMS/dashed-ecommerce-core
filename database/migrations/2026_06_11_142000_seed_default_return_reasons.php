<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Migrations\Migration;
use Dashed\DashedEcommerceCore\Support\DefaultReturnReasons;

return new class () extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('dashed__return_reasons')) {
            return;
        }

        DefaultReturnReasons::seed();
    }

    public function down(): void
    {
        // Bewust geen verwijdering: de beheerder kan redenen hebben aangepast.
    }
};
