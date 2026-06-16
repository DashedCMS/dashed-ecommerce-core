<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    /**
     * Some code paths historically stored an empty string '' instead of NULL for
     * products without an EAN, which hid them from the GS1 export / EAN import
     * (those filtered on NULL only). Normalise blanks to NULL so "no EAN" is
     * represented consistently.
     */
    public function up(): void
    {
        DB::table('dashed__products')->where('ean', '')->update(['ean' => null]);
    }

    public function down(): void
    {
        //
    }
};
