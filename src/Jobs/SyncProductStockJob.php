<?php

namespace Dashed\DashedEcommerceCore\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Dashed\DashedEcommerceCore\Models\Product;

class SyncProductStockJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public $tries = 3;
    public $timeout = 60;

    public Product $product;

    public function __construct(Product $product)
    {
        $this->product = $product;
    }

    public function handle(): void
    {
        $group = $this->product->stockSyncGroup();

        if (! $group) {
            return;
        }

        $sourceProduct = $this->product->getStockSourceProduct();
        $triggerProduct = $this->product;

        // If the trigger product is a receiver, sync its stock value back to source first
        if ($triggerProduct->id !== $sourceProduct->id) {
            $sourceProduct->stock = $triggerProduct->stock;
            $sourceProduct->saveQuietly();
        }

        $stockValue = $sourceProduct->fresh()->stock;

        // Sync stock to all other products in the group
        foreach ($group as $product) {
            if ($product->id === $triggerProduct->id) {
                continue; // Skip the trigger - it already has the right value
            }

            $product = $product->fresh();
            $product->stock = $stockValue;
            $product->saveQuietly();
        }

        // Recalculate total_stock and in_stock for all products in the group
        foreach ($group as $product) {
            $product = $product->fresh();
            $product->calculateStock();
        }
    }
}
