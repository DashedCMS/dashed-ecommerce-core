<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('dashed__order_payments', function (Blueprint $table) {
            if (! Schema::hasColumn('dashed__order_payments', 'psp_request')) {
                $table->json('psp_request')->nullable();
            }

            if (! Schema::hasColumn('dashed__order_payments', 'psp_response')) {
                $table->json('psp_response')->nullable();
            }
        });
    }

    public function down(): void
    {
        //
    }
};
