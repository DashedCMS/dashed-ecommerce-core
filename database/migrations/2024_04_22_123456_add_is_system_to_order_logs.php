<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('dashed__order_logs', function (Blueprint $table) {
            $table->boolean('is_system')
                ->after('user_id')
                ->default(0);
            $table->text('url')
                ->nullable()
                ->after('is_system');
        });

        foreach (\Dashed\DashedEcommerceCore\Models\OrderLog::all() as $orderLog) {
            $orderLog->is_system = str($orderLog->tag)->contains('system');
            $orderLog->save();
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_extra_options', function (Blueprint $table) {
            //
        });
    }
};
