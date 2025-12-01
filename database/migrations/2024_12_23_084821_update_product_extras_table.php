<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Dashed\DashedEcommerceCore\Models\ProductExtra;

return new class () extends Migration {
    public function up(): void
    {
        // Nieuwe velden op extras
        Schema::table('dashed__product_extras', function (Blueprint $table) {
            $table->integer('order')->default(0);
            $table->boolean('global')->default(false);
        });

        // Pivot
        Schema::create('dashed__product_extra_product', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')
                ->constrained('dashed__products')
                ->cascadeOnDelete();

            $table->foreignId('product_extra_id')
                ->constrained('dashed__product_extras')
                ->cascadeOnDelete();

            // voorkom dubbele koppelingen
            $table->unique(['product_id', 'product_extra_id'], 'pep_product_extra_unique');
        });

        // Data migreren van oude kolom naar pivot
        foreach (ProductExtra::withTrashed()->get(['id', 'product_id']) as $extra) {
            if (! is_null($extra->product_id)) {
                DB::table('dashed__product_extra_product')->insert([
                    'product_id' => $extra->product_id,
                    'product_extra_id' => $extra->id,
                ]);
            }
        }

        // Foreign key op product_id droppen (naam laten afleiden)
        Schema::table('dashed__product_extras', function (Blueprint $table) {
            if (Schema::hasColumn('dashed__product_extras', 'product_id')) {
                // Probeer generieke drop (werkt in 99% van de gevallen)
                try {
                    $table->dropForeign(['product_id']);
                } catch (\Throwable $e) {
                    // Als de naam custom is, resolve de echte naam en drop met raw SQL
                    $constraint = DB::selectOne("
                        SELECT CONSTRAINT_NAME
                        FROM information_schema.KEY_COLUMN_USAGE
                        WHERE TABLE_SCHEMA = DATABASE()
                          AND TABLE_NAME = 'dashed__product_extras'
                          AND COLUMN_NAME = 'product_id'
                          AND REFERENCED_TABLE_NAME IS NOT NULL
                        LIMIT 1
                    ");
                    if ($constraint && isset($constraint->CONSTRAINT_NAME)) {
                        DB::statement("ALTER TABLE `dashed__product_extras` DROP FOREIGN KEY `{$constraint->CONSTRAINT_NAME}`");
                    }
                }
            }
        });

        // Kolom pas nu droppen
        Schema::table('dashed__product_extras', function (Blueprint $table) {
            if (Schema::hasColumn('dashed__product_extras', 'product_id')) {
                $table->dropColumn('product_id');
            }
        });
    }

    public function down(): void
    {
        // Kolom terug (nullable, want pivot → single value is ambigu)
        Schema::table('dashed__product_extras', function (Blueprint $table) {
            if (! Schema::hasColumn('dashed__product_extras', 'product_id')) {
                $table->foreignId('product_id')
                    ->nullable()
                    ->constrained('dashed__products')
                    ->nullOnDelete();
            }
            // oude velden terugdraaien kan, maar laten staan is doorgaans veilig
        });

        // Best-effort terugschrijven van eerste koppeling uit pivot
        $pairs = DB::table('dashed__product_extra_product')
            ->select('product_extra_id', DB::raw('MIN(product_id) as product_id'))
            ->groupBy('product_extra_id')
            ->get();

        foreach ($pairs as $row) {
            DB::table('dashed__product_extras')
                ->where('id', $row->product_extra_id)
                ->update(['product_id' => $row->product_id]);
        }

        // Pivot droppen
        Schema::dropIfExists('dashed__product_extra_product');

        // (Desgewenst) unieke index verwijderen gebeurt automatisch bij dropIfExists
    }
};
