<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('dashed__order_handled_flows')) {
            Schema::create('dashed__order_handled_flows', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->boolean('is_active')->default(true);
                $table->string('discount_prefix', 20)->nullable();
                $table->unsignedSmallInteger('skip_if_recently_ordered_within_days')->nullable()->default(30);
                $table->boolean('cancel_on_link_click')->default(true);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('dashed__order_handled_flows');
    }
};
