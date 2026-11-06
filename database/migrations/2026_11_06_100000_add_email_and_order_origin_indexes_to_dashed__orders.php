<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        // Zoeken op e-mailadres (bestelling bekijken, kortingscode per klant) en de
        // Bol-/Etsy-controles op order_origin waren een volledige tabelscan.
        $indexed = collect(Schema::getIndexes('dashed__orders'))
            ->map(fn (array $index) => $index['columns'][0] ?? null);

        Schema::table('dashed__orders', function (Blueprint $table) use ($indexed) {
            if (! $indexed->contains('email')) {
                $table->index('email');
            }
            if (! $indexed->contains('order_origin')) {
                $table->index('order_origin');
            }
        });
    }

    public function down(): void
    {
        $names = collect(Schema::getIndexes('dashed__orders'))->pluck('name');

        Schema::table('dashed__orders', function (Blueprint $table) use ($names) {
            foreach (['email', 'order_origin'] as $column) {
                if ($names->contains("dashed__orders_{$column}_index")) {
                    $table->dropIndex([$column]);
                }
            }
        });
    }
};
