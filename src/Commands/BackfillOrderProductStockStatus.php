<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Dashed\DashedEcommerceCore\Models\OrderProduct;

class BackfillOrderProductStockStatus extends Command
{
    protected $signature = 'dashed:backfill-order-product-stock-status {--dry-run : Alleen tellen, niets schrijven} {--all : Herbereken ook niet-NULL rijen volgens dezelfde proxy}';

    protected $description = 'Vul stock_status_at_order op order-regels (best-effort) via de opgeslagen is_pre_order-vlag en de voorraadinstelling van het product';

    public function handle(): int
    {
        $all = (bool) $this->option('all');

        if ($this->option('dry-run')) {
            $counts = self::counts($all);
            $this->info('[dry-run] Niets geschreven.');
            $this->line("Zou out_of_stock zetten: {$counts['out_of_stock']}");
            $this->line("Zou in_stock zetten:     {$counts['in_stock']}");
            if ($counts['remaining_null'] !== null) {
                $this->line("Zou NULL blijven:        {$counts['remaining_null']}");
            }

            return self::SUCCESS;
        }

        $result = self::backfill($all);

        $this->info('Backfill klaar.');
        $this->line("out_of_stock gezet: {$result['out_of_stock']}");
        $this->line("in_stock gezet:     {$result['in_stock']}");
        $this->line("NULL gebleven:      {$result['remaining_null']}");

        return self::SUCCESS;
    }

    /**
     * Vul `stock_status_at_order` best-effort op bestaande order-regels. Er is
     * geen voorraadhistorie in ec-core, dus we leiden de stand op besteldatum af
     * uit de reeds opgeslagen `is_pre_order`-vlag en de voorraadinstelling van
     * het gekoppelde product. Pure DB-updates (geen model-events, dus de
     * OrderProduct::creating-hook en activity-log triggeren niet). Idempotent:
     * standaard alleen rijen zonder waarde (`stock_status_at_order IS NULL`).
     * Wordt zowel door de artisan-command als door de deploy-migratie gebruikt.
     *
     * @return array{out_of_stock:int, in_stock:int, remaining_null:int}
     */
    public static function backfill(bool $all = false): array
    {
        // Pre-order op besteldatum -> niet (volledig) op voorraad.
        $outOfStock = self::query($all)
            ->where('is_pre_order', 1)
            ->update(['stock_status_at_order' => OrderProduct::STOCK_STATUS_OUT_OF_STOCK]);

        // Geen pre-order + gekoppeld voorraadbeheer-product -> was op voorraad.
        $inStock = self::query($all)
            ->where('is_pre_order', 0)
            ->whereIn('product_id', self::useStockProductIds())
            ->update(['stock_status_at_order' => OrderProduct::STOCK_STATUS_IN_STOCK]);

        $remainingNull = DB::table('dashed__order_products')
            ->whereNull('stock_status_at_order')
            ->count();

        return [
            'out_of_stock' => (int) $outOfStock,
            'in_stock' => (int) $inStock,
            'remaining_null' => $remainingNull,
        ];
    }

    /**
     * Telt (zonder te schrijven) hoeveel rijen elke tak zou raken.
     *
     * @return array{out_of_stock:int, in_stock:int, remaining_null:?int}
     */
    public static function counts(bool $all = false): array
    {
        $outOfStock = (int) self::query($all)->where('is_pre_order', 1)->count();
        $inStock = (int) self::query($all)
            ->where('is_pre_order', 0)
            ->whereIn('product_id', self::useStockProductIds())
            ->count();

        $totalNull = DB::table('dashed__order_products')->whereNull('stock_status_at_order')->count();

        return [
            'out_of_stock' => $outOfStock,
            'in_stock' => $inStock,
            'remaining_null' => $all ? null : max(0, $totalNull - $outOfStock - $inStock),
        ];
    }

    /**
     * Basisquery op de order-regels; standaard alleen de nog lege rijen.
     */
    private static function query(bool $all): \Illuminate\Database\Query\Builder
    {
        return DB::table('dashed__order_products')
            ->when(! $all, fn ($q) => $q->whereNull('stock_status_at_order'));
    }

    /**
     * Subquery: id's van producten met voorraadbeheer aan (ook getrashte
     * producten tellen mee).
     */
    private static function useStockProductIds(): \Closure
    {
        return fn ($query) => $query
            ->select('id')
            ->from('dashed__products')
            ->where('use_stock', 1);
    }
}
