<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('dashed__orders', function (Blueprint $table) {
            // Wanneer de browser-events voor de aankoop (GTM purchase, Meta
            // pixel, TikTok) zijn afgegaan. Gezet bij de eerste weergave van
            // de bedankpagina, zodat herladen of de link uit de bevestigingsmail
            // geen tweede conversie meer meet.
            $table->timestamp('purchase_tracked_at')->nullable();
        });

        // Orders die al betaald zijn, zijn al gemeten: zonder deze vulling vuurt
        // een oude maillink na de release alsnog een conversie af.
        DB::table('dashed__orders')
            ->whereIn('status', ['paid', 'waiting_for_confirmation', 'partially_paid', 'return'])
            ->update(['purchase_tracked_at' => DB::raw('updated_at')]);
    }

    public function down(): void
    {
        Schema::table('dashed__orders', function (Blueprint $table) {
            $table->dropColumn('purchase_tracked_at');
        });
    }
};
