<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('dashed__pos_carts', function (Blueprint $table) {
            $table->foreignId('shipping_method_id')
                ->nullable()
                ->constrained('dashed__shipping_methods')
                ->nullOnDelete();

            $table->string('first_name')
                ->nullable();
            $table->string('last_name')
                ->nullable();
            $table->string('email')
                ->nullable();
            $table->string('street')
                ->nullable();
            $table->string('house_nr')
                ->nullable();
            $table->string('zip_code')
                ->nullable();
            $table->string('city')
                ->nullable();
            $table->string('country')
                ->nullable();
            $table->string('company')
                ->nullable();
            $table->string('btw_id')
                ->nullable();
            $table->string('invoice_street')
                ->nullable();
            $table->string('invoice_house_nr')
                ->nullable();
            $table->string('invoice_zip_code')
                ->nullable();
            $table->string('invoice_city')
                ->nullable();
            $table->string('invoice_country')
                ->nullable();
            $table->text('note')
                ->nullable();
            $table->json('custom_fields')
                ->nullable();
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
