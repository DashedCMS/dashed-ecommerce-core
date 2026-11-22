<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('dashed__orders', function (Blueprint $table) {
            // Handmatig "on hold": de bestelling mag (nog) niet uitgeleverd
            // worden, bijv. omdat we op een reactie van de klant wachten.
            // Los van fulfillment_status, zodat die bewaard blijft.
            if (! Schema::hasColumn('dashed__orders', 'on_hold_at')) {
                $table->timestamp('on_hold_at')->nullable()->index();
            }
            if (! Schema::hasColumn('dashed__orders', 'on_hold_reason')) {
                $table->text('on_hold_reason')->nullable();
            }
            if (! Schema::hasColumn('dashed__orders', 'on_hold_by')) {
                $table->unsignedBigInteger('on_hold_by')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('dashed__orders', function (Blueprint $table) {
            $table->dropIndex(['on_hold_at']);
            $table->dropColumn(['on_hold_at', 'on_hold_reason', 'on_hold_by']);
        });
    }
};
