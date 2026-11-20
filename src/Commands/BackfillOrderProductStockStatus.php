<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillOrderProductStockStatus extends Command
{
    protected $signature = 'dashed:backfill-order-product-stock-status {--dry-run : Alleen tellen, niets schrijven} {--all : Herbereken ook niet-NULL rijen volgens dezelfde proxy}';

    protected $description = 'Vul stock_status_at_order op order-regels (best-effort) via de opgeslagen is_pre_order-vlag en de voorraadinstelling van het product';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $all = (bool) $this->option('all');

        // Subquery: producten met voorraadbeheer aan (ook getrashte producten tellen mee).
        $useStockProductIds = fn ($query) => $query
            ->select('id')
            ->from('dashed__products')
            ->where('use_stock', 1);

        // Tak 1: pre-order op besteldatum -> out_of_stock.
        $outOfStockQuery = DB::table('dashed__order_products')
            ->when(! $all, fn ($q) => $q->whereNull('stock_status_at_order'))
            ->where('is_pre_order', 1);

        // Tak 2: geen pre-order + gekoppeld product met voorraadbeheer -> in_stock.
        $inStockQuery = DB::table('dashed__order_products')
            ->when(! $all, fn ($q) => $q->whereNull('stock_status_at_order'))
            ->where('is_pre_order', 0)
            ->whereIn('product_id', $useStockProductIds);

        if ($dryRun) {
            $outOfStock = (clone $outOfStockQuery)->count();
            $inStock = (clone $inStockQuery)->count();

            // Overgeslagen (blijft NULL): totaal NULL minus wat we zouden zetten.
            $totalNull = DB::table('dashed__order_products')->whereNull('stock_status_at_order')->count();
            $skipped = $all ? null : max(0, $totalNull - $outOfStock - $inStock);

            $this->info('[dry-run] Niets geschreven.');
            $this->line("Zou out_of_stock zetten: {$outOfStock}");
            $this->line("Zou in_stock zetten:     {$inStock}");
            if ($skipped !== null) {
                $this->line("Zou NULL blijven:        {$skipped}");
            }

            return self::SUCCESS;
        }

        $outOfStock = $outOfStockQuery->update(['stock_status_at_order' => 'out_of_stock']);
        $inStock = $inStockQuery->update(['stock_status_at_order' => 'in_stock']);

        $skipped = DB::table('dashed__order_products')->whereNull('stock_status_at_order')->count();

        $this->info('Backfill klaar.');
        $this->line("out_of_stock gezet: {$outOfStock}");
        $this->line("in_stock gezet:     {$inStock}");
        $this->line("NULL gebleven:      {$skipped}");

        return self::SUCCESS;
    }
}
