<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('dashed__order_handled_flows')) {
            return;
        }

        Schema::table('dashed__order_handled_flows', function (Blueprint $table) {
            if (! Schema::hasColumn('dashed__order_handled_flows', 'review_urls')) {
                // Bewaart een lijst van review-URLs met optioneel label en weight
                // voor A/B-testen. Vorm: [{label, url, weight}, ...]
                $table->json('review_urls')->nullable()->after('discount_prefix');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('dashed__order_handled_flows')) {
            return;
        }

        Schema::table('dashed__order_handled_flows', function (Blueprint $table) {
            if (Schema::hasColumn('dashed__order_handled_flows', 'review_urls')) {
                $table->dropColumn('review_urls');
            }
        });
    }
};
