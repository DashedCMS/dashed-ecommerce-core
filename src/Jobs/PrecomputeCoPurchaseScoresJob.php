<?php

namespace Dashed\DashedEcommerceCore\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Support\Carbon;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedCore\Jobs\Concerns\HandlesQueueFailures;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\OrderProduct;
use Dashed\DashedEcommerceCore\Models\ProductCoPurchase;

/**
 * Materialises product co-purchase pairs (used by FrequentlyBoughtTogetherStrategy).
 *
 * Two modes:
 *   - `incremental` (default, scheduled nightly): only re-process orders
 *     updated since the most recent `last_computed_at` on the table.
 *   - `full`: re-process every order within the configured lookback window
 *     and prune rows older than 7 days that didn't get a fresh update.
 *
 * Score formula: cosine-like  `co_count / sqrt(count_a × count_b)`
 *  where `count_x` = total times product x was sold over the lookback.
 *
 * Lookback default: 365 days, overridable via
 *   Customsetting::set('recommendations_copurchase_window_days', 30).
 */
final class PrecomputeCoPurchaseScoresJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;
    use HandlesQueueFailures;

    public function __construct(public string $mode = 'incremental')
    {
    }

    public function extraLogContext(): array
    {
        return ['mode' => $this->mode, 'tag' => 'recommendations'];
    }

    public function handle(): void
    {
        $windowDays = (int) Customsetting::get('recommendations_copurchase_window_days', null, 365);
        $windowStart = Carbon::now()->subDays(max(1, $windowDays));

        // Incremental cutoff: skip orders we already processed (their pair
        // rows have a fresh last_computed_at).
        $cutoff = $windowStart;
        if ($this->mode === 'incremental') {
            $latest = ProductCoPurchase::max('last_computed_at');
            if ($latest) {
                $cutoff = max($windowStart, Carbon::parse($latest));
            }
        }

        $orders = Order::query()
            ->paidStatusses()
            ->where('updated_at', '>=', $cutoff)
            ->select('id')
            ->pluck('id');

        if ($orders->isEmpty() && $this->mode === 'incremental') {
            return;
        }

        // Build pair counts: ["a:b" => coCount]
        $pairCounts = [];
        $productCounts = [];

        // Fetch grouped product counts for the whole window in one go (cosine denominator).
        $countsRows = OrderProduct::query()
            ->whereIn('order_id', function ($q) use ($windowStart) {
                $q->select('id')->from('dashed__orders')
                    ->where('updated_at', '>=', $windowStart);
            })
            ->whereNotNull('product_id')
            ->groupBy('product_id')
            ->selectRaw('product_id, COUNT(*) as c')
            ->get();
        foreach ($countsRows as $row) {
            $productCounts[(int) $row->product_id] = (int) $row->c;
        }

        // Iterate orders to accumulate pair counts.
        Order::query()
            ->whereIn('id', $orders)
            ->with(['orderProducts:id,order_id,product_id'])
            ->chunkById(200, function ($chunk) use (&$pairCounts) {
                foreach ($chunk as $order) {
                    $ids = $order->orderProducts
                        ->pluck('product_id')
                        ->filter()
                        ->map(fn ($v) => (int) $v)
                        ->unique()
                        ->values()
                        ->all();

                    $n = count($ids);
                    for ($i = 0; $i < $n; $i++) {
                        for ($j = $i + 1; $j < $n; $j++) {
                            [$a, $b] = $this->pair($ids[$i], $ids[$j]);
                            $key = "$a:$b";
                            $pairCounts[$key] = ($pairCounts[$key] ?? 0) + 1;
                        }
                    }
                }
            });

        $now = Carbon::now();
        foreach (array_chunk(array_keys($pairCounts), 500, true) as $batchKeys) {
            $rows = [];
            foreach ($batchKeys as $key) {
                [$a, $b] = array_map('intval', explode(':', $key));
                $co = $pairCounts[$key];
                $countA = $productCounts[$a] ?? 0;
                $countB = $productCounts[$b] ?? 0;
                $denominator = sqrt(max(1, $countA) * max(1, $countB));
                $score = $denominator > 0 ? min(1.0, $co / $denominator) : 0.0;

                $rows[] = [
                    'product_a_id' => $a,
                    'product_b_id' => $b,
                    'co_count' => $co,
                    'score' => round($score, 4),
                    'last_computed_at' => $now,
                    'updated_at' => $now,
                ];
            }
            ProductCoPurchase::query()->upsert(
                $rows,
                ['product_a_id', 'product_b_id'],
                ['co_count', 'score', 'last_computed_at', 'updated_at'],
            );
        }

        // Full-mode prune: drop rows that didn't get refreshed this run
        // (stale > 7 days). Incremental mode never prunes.
        if ($this->mode === 'full') {
            ProductCoPurchase::query()
                ->where('last_computed_at', '<', $now->copy()->subDays(7))
                ->delete();
        }
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function pair(int $x, int $y): array
    {
        return $x < $y ? [$x, $y] : [$y, $x];
    }
}
