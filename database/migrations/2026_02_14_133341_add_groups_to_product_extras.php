<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('dashed__product_extra_product_groups', function (Blueprint $table) {
            $table->id();

            $table->foreignId('product_extra_id')
                ->constrained('dashed__product_extras')
                ->cascadeOnDelete();
            $table->foreignId('product_group_id')
                ->constrained(
                    'dashed__product_groups',
                    'id',
                    'product_group_fk'
                )
                ->cascadeOnDelete();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
