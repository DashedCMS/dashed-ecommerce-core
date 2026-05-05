<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('dashed__order_handled_flow_steps')) {
            Schema::create('dashed__order_handled_flow_steps', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('flow_id');
                $table->unsignedInteger('sort_order')->default(0);
                $table->unsignedInteger('send_after_minutes')->default(20160); // 14 dagen
                $table->boolean('is_active')->default(true);
                $table->json('subject')->nullable();
                $table->json('blocks')->nullable();
                $table->timestamps();

                $table->foreign('flow_id')
                    ->references('id')
                    ->on('dashed__order_handled_flows')
                    ->cascadeOnDelete();

                $table->index(['flow_id', 'sort_order']);
                $table->index(['flow_id', 'is_active']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('dashed__order_handled_flow_steps');
    }
};
