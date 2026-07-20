<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    /**
     * De claim-check (recentlyRan()) filtert altijd op rule_id + subject +
     * status en sorteert/range't op created_at — de bestaande indexes
     * (subject_type+subject_id, rule_id+created_at) dekken dat niet
     * gezamenlijk. Deze index is precies op dat pad gesneden.
     */
    public function up(): void
    {
        if (! Schema::hasTable('dashed__automation_rule_runs')) {
            return;
        }

        Schema::table('dashed__automation_rule_runs', function (Blueprint $table) {
            $table->index(
                ['rule_id', 'subject_type', 'subject_id', 'status', 'created_at'],
                'automation_rule_runs_claim_lookup_index',
            );
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('dashed__automation_rule_runs')) {
            return;
        }

        Schema::table('dashed__automation_rule_runs', function (Blueprint $table) {
            $table->dropIndex('automation_rule_runs_claim_lookup_index');
        });
    }
};
