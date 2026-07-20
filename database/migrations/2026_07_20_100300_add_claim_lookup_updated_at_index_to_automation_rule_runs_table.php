<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    /**
     * recentlyRan() meet het hergebruik-venster voor afgeronde (success/
     * failed) rijen sinds kort vanaf `updated_at` in plaats van
     * `created_at` (zie AutomationEngine::recentlyRan()) — de rij wordt
     * geclaimd bij `created_at`, maar pas afgerond bij `updated_at`, en het
     * venster hoort pas ná afronding te lopen. De bestaande
     * `automation_rule_runs_claim_lookup_index` eindigt op `created_at` en
     * dekt dat afgeronde-rijen-pad dus niet meer. Deze index is precies op
     * dat (nieuwe) pad gesneden, naast de bestaande index (die nog steeds
     * het running-rijen-pad dekt, dat wél op `created_at` blijft filteren).
     */
    public function up(): void
    {
        if (! Schema::hasTable('dashed__automation_rule_runs')) {
            return;
        }

        Schema::table('dashed__automation_rule_runs', function (Blueprint $table) {
            $table->index(
                ['rule_id', 'subject_type', 'subject_id', 'status', 'updated_at'],
                'automation_rule_runs_claim_lookup_updated_at_index',
            );
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('dashed__automation_rule_runs')) {
            return;
        }

        Schema::table('dashed__automation_rule_runs', function (Blueprint $table) {
            $table->dropIndex('automation_rule_runs_claim_lookup_updated_at_index');
        });
    }
};
