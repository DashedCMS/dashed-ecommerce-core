<?php

namespace Dashed\DashedEcommerceCore\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Dashed\DashedEcommerceCore\Models\Product;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

/**
 * Lichte, gerichte prijs-herberekening: draait alleen Product::calculatePrices()
 * voor de opgegeven producten. Wordt gebruikt na een prijsgroep- of
 * prijs-per-gebruiker-wijziging zodat niet de volledige product-rebuild
 * (voorraad, filters, bundels, indexering) over de hele catalogus loopt.
 */
class RecalculateProductPricesJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public $tries = 5;
    public $timeout = 1200;

    /**
     * @param  array<int>  $productIds
     */
    public function __construct(public array $productIds)
    {
    }

    public function handle(): void
    {
        $ids = array_values(array_unique(array_filter($this->productIds)));
        if (! $ids) {
            return;
        }

        Product::whereIn('id', $ids)->chunkById(100, function ($products) {
            foreach ($products as $product) {
                DB::transaction(fn () => $product->calculatePrices(), 5);
            }
        });
    }
}
