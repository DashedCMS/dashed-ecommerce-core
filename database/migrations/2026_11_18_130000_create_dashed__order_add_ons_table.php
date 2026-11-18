<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('dashed__order_add_ons')) {
            return;
        }

        // Bijbestelling: een betaalde order die in dezelfde doos meegaat met
        // een eerdere order (parent). Status linked = meegestuurd, separate =
        // toch los verzonden (reden in reason).
        Schema::create('dashed__order_add_ons', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id')->unique();
            $table->unsignedBigInteger('parent_order_id')->index();
            $table->string('status', 20);
            $table->string('reason')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('handled_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashed__order_add_ons');
    }
};
