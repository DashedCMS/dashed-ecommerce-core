<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('dashed__wishlists', function (Blueprint $table) {
            $table->id();
            $table->uuid('token')->unique();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('email')->nullable()->index();
            $table->uuid('share_token')->nullable()->unique();
            $table->string('locale', 10)->nullable();
            $table->string('site_id')->nullable();
            $table->timestamp('last_activity_at')->nullable()->index();
            $table->timestamp('flow_cooldown_until')->nullable();
            $table->timestamps();
        });

        Schema::create('dashed__wishlist_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wishlist_id')->constrained('dashed__wishlists')->cascadeOnDelete();
            $table->unsignedBigInteger('product_id')->index();
            $table->decimal('price_at_add', 10, 2)->nullable();
            $table->timestamps();

            $table->unique(['wishlist_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashed__wishlist_items');
        Schema::dropIfExists('dashed__wishlists');
    }
};
