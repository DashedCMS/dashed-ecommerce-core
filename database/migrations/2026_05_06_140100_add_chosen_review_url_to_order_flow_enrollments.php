<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('dashed__order_flow_enrollments')) {
            return;
        }

        Schema::table('dashed__order_flow_enrollments', function (Blueprint $table) {
            // Slaat per inschrijving de gekozen review-URL vast zodat alle stappen
            // van de flow voor dezelfde klant dezelfde URL gebruiken (true A/B
            // test per inschrijving). Het label maakt platformsplitsing voor
            // statistieken mogelijk.
            if (! Schema::hasColumn('dashed__order_flow_enrollments', 'chosen_review_url_label')) {
                $table->string('chosen_review_url_label')->nullable()->after('cancelled_reason');
                $table->index('chosen_review_url_label');
            }

            if (! Schema::hasColumn('dashed__order_flow_enrollments', 'chosen_review_url')) {
                $table->string('chosen_review_url', 2048)->nullable()->after('chosen_review_url_label');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('dashed__order_flow_enrollments')) {
            return;
        }

        Schema::table('dashed__order_flow_enrollments', function (Blueprint $table) {
            if (Schema::hasColumn('dashed__order_flow_enrollments', 'chosen_review_url_label')) {
                $table->dropIndex(['chosen_review_url_label']);
                $table->dropColumn('chosen_review_url_label');
            }

            if (Schema::hasColumn('dashed__order_flow_enrollments', 'chosen_review_url')) {
                $table->dropColumn('chosen_review_url');
            }
        });
    }
};
