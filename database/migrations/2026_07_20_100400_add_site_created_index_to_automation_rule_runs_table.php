<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    /**
     * Het uitvoerlog-scherm in de app (AutomationRuleController::runs()) leest
     * deze tabel met `where('site_id', …)` en sorteert op
     * `created_at DESC, id DESC`. De bestaande claim-lookup-indexen beginnen
     * met `rule_id` en dekken dat site-brede, op-datum-gesorteerde pad niet —
     * zonder deze index wordt het bij een groeiende log een filesort over een
     * volledige site-scan. `(site_id, created_at, id)` dekt zowel het filter
     * als de sorteervolgorde, en helpt ook de optionele `rule_id`-variant
     * (die deelt de `site_id`-prefix).
     */
    public function up(): void
    {
        if (! Schema::hasTable('dashed__automation_rule_runs')) {
            return;
        }

        Schema::table('dashed__automation_rule_runs', function (Blueprint $table) {
            $table->index(
                ['site_id', 'created_at', 'id'],
                'automation_rule_runs_site_created_index',
            );
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('dashed__automation_rule_runs')) {
            return;
        }

        Schema::table('dashed__automation_rule_runs', function (Blueprint $table) {
            $table->dropIndex('automation_rule_runs_site_created_index');
        });
    }
};
