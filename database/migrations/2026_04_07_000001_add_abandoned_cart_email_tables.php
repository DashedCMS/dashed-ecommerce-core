<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('dashed__carts', 'abandoned_email')) {
            Schema::table('dashed__carts', function (Blueprint $table) {
                $table->string('abandoned_email')->nullable()->after('meta');
            });
        }

        if (! Schema::hasTable('dashed__abandoned_cart_emails')) {
            Schema::create('dashed__abandoned_cart_emails', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('cart_id');
                $table->string('email');
                $table->unsignedTinyInteger('email_number'); // 1, 2, 3
                $table->timestamp('sent_at')->nullable();
                $table->timestamp('cancelled_at')->nullable();
                $table->unsignedBigInteger('discount_code_id')->nullable();
                $table->timestamps();

                $table->index('cart_id');
                $table->index(['cart_id', 'email_number']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('dashed__abandoned_cart_emails');

        if (Schema::hasColumn('dashed__carts', 'abandoned_email')) {
            Schema::table('dashed__carts', function (Blueprint $table) {
                $table->dropColumn('abandoned_email');
            });
        }
    }
};
