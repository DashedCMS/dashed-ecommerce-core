<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('dashed__order_handled_clicks')) {
            Schema::create('dashed__order_handled_clicks', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('order_id');
                $table->unsignedBigInteger('flow_step_id');
                $table->string('link_type', 50);
                $table->timestamp('clicked_at')->useCurrent();
                $table->timestamps();

                $table->foreign('order_id')
                    ->references('id')
                    ->on('dashed__orders')
                    ->cascadeOnDelete();

                $table->foreign('flow_step_id')
                    ->references('id')
                    ->on('dashed__order_handled_flow_steps')
                    ->cascadeOnDelete();

                $table->index('order_id');
                $table->index('flow_step_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('dashed__order_handled_clicks');
    }
};
