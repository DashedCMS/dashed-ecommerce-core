<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('dashed__order_flow_enrollments')) {
            Schema::create('dashed__order_flow_enrollments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('order_id')
                    ->constrained('dashed__orders')
                    ->cascadeOnDelete();
                $table->foreignId('flow_id')
                    ->constrained('dashed__order_handled_flows')
                    ->cascadeOnDelete();
                $table->timestamp('started_at');
                $table->timestamp('cancelled_at')->nullable();
                $table->string('cancelled_reason')->nullable();
                $table->timestamps();

                // 1 enrollment per (order, flow) combinatie. Voorkomt dat dezelfde
                // flow tweemaal voor dezelfde order start.
                $table->unique(['order_id', 'flow_id'], 'order_flow_enrollments_order_flow_unique');
                $table->index('cancelled_at');
            });
        }

        // Backfill: voor elke bestaande order met handled_flow_started_at IS NOT NULL
        // maken we een enrollment-rij. We koppelen aan de flow die op dat moment
        // (best-effort) actief was. Voorkeur: er is precies 1 actieve flow ->
        // gebruik die. Anders: pak de eerste flow op id. Bestaan er nog geen flows,
        // dan slaan we deze order over.
        if (! Schema::hasTable('dashed__orders') || ! Schema::hasTable('dashed__order_handled_flows')) {
            return;
        }

        $activeFlowsCount = DB::table('dashed__order_handled_flows')->where('is_active', true)->count();
        if ($activeFlowsCount === 1) {
            $flowId = DB::table('dashed__order_handled_flows')
                ->where('is_active', true)
                ->orderBy('id')
                ->value('id');
        } else {
            $flowId = DB::table('dashed__order_handled_flows')->orderBy('id')->value('id');
        }

        if (! $flowId) {
            return;
        }

        $hasStartedColumn = Schema::hasColumn('dashed__orders', 'handled_flow_started_at');
        $hasCancelledColumn = Schema::hasColumn('dashed__orders', 'handled_flow_cancelled_at');
        if (! $hasStartedColumn) {
            return;
        }

        $now = now();

        DB::table('dashed__orders')
            ->select(['id', 'handled_flow_started_at', $hasCancelledColumn ? 'handled_flow_cancelled_at' : DB::raw('NULL as handled_flow_cancelled_at')])
            ->whereNotNull('handled_flow_started_at')
            ->orderBy('id')
            ->chunkById(500, function ($orders) use ($flowId, $now) {
                $rows = [];
                foreach ($orders as $order) {
                    $cancelledAt = $order->handled_flow_cancelled_at ?? null;
                    $rows[] = [
                        'order_id' => $order->id,
                        'flow_id' => $flowId,
                        'started_at' => $order->handled_flow_started_at,
                        'cancelled_at' => $cancelledAt,
                        'cancelled_reason' => $cancelledAt ? 'migrated' : null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                if ($rows) {
                    // insertOrIgnore zodat opnieuw uitvoeren of partial-runs niet crashen.
                    DB::table('dashed__order_flow_enrollments')->insertOrIgnore($rows);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashed__order_flow_enrollments');
    }
};
