<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('dashed__carts', function (Blueprint $table) {
            $table->id();

            // Identiteit
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->uuid('token')->unique(); // guest + fallback key

            // Context
            $table->string('type')->default('default')->index(); // default, wishlist, etc
            $table->string('locale', 10)->nullable()->index();
            $table->string('currency', 3)->nullable()->index();
            $table->unsignedBigInteger('store_id')->nullable()->index(); // als je multi-store hebt

            // Checkout keuzes
            $table->foreignId('discount_code_id')->nullable()->constrained('dashed__discount_codes')->nullOnDelete();
            $table->foreignId('shipping_method_id')->nullable()->constrained('dashed__shipping_methods')->nullOnDelete();
            $table->unsignedBigInteger('shipping_zone_id')->nullable()->index(); // als dit niet FK is in je setup
            $table->foreignId('payment_method_id')->nullable()->constrained('dashed__payment_methods')->nullOnDelete();
            $table->foreignId('deposit_payment_method_id')->nullable()->constrained('dashed__payment_methods')->nullOnDelete();

            // Handige vrije ruimte voor later
            $table->json('meta')->nullable();

            $table->timestamps();

            // Snel zoeken: user carts per type
            $table->index(['user_id', 'type']);
        });

        Schema::create('dashed__cart_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('cart_id')->constrained('dashed__carts')->cascadeOnDelete();

            // Product is nullable voor customProduct items
            $table->foreignId('product_id')->nullable()->constrained('dashed__products')->nullOnDelete();

            // Snapshot velden (super handig)
            $table->string('name')->nullable(); // productnaam op moment van toevoegen
            $table->decimal('unit_price', 12, 2)->nullable(); // optioneel snapshot

            $table->unsignedInteger('quantity')->default(1);

            // Alles wat je nu in $cartItem->options stopt
            $table->json('options')->nullable();

            $table->timestamps();

            // Performance: veelgebruikte queries
            $table->index(['cart_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('carts');
    }
};
