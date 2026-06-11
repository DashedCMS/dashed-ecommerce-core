<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Migrations\Migration;
use Dashed\DashedEcommerceCore\Support\DefaultReturnPage;

return new class () extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('dashed__pages')) {
            return;
        }

        DefaultReturnPage::createIfMissing();
    }

    public function down(): void
    {
        // Bewust geen verwijdering: de beheerder kan de pagina zelf hebben aangepast.
    }
};
