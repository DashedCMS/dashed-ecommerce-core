<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('dashed__abandoned_cart_emails') && ! Schema::hasColumn('dashed__abandoned_cart_emails', 'clicked_at')) {
            Schema::table('dashed__abandoned_cart_emails', function (Blueprint $table) {
                $table->timestamp('clicked_at')->nullable()->after('sent_at');
                $table->unsignedBigInteger('order_id')->nullable()->after('discount_code_id');
                $table->timestamp('converted_at')->nullable()->after('order_id');
            });
        }

        if (! Schema::hasTable('dashed__abandoned_cart_clicks')) {
            Schema::create('dashed__abandoned_cart_clicks', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('abandoned_cart_email_id');
                $table->string('link_type', 50); // button, product
                $table->timestamps();

                $table->index('abandoned_cart_email_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('dashed__abandoned_cart_clicks');

        if (Schema::hasColumn('dashed__abandoned_cart_emails', 'clicked_at')) {
            Schema::table('dashed__abandoned_cart_emails', function (Blueprint $table) {
                $table->dropColumn(['clicked_at', 'order_id', 'converted_at']);
            });
        }
    }
};
