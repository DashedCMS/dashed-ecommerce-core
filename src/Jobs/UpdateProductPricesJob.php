<?php

namespace Dashed\DashedEcommerceCore\Jobs;

use Throwable;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Models\ProductGroup;
use Dashed\DashedEcommerceCore\Support\BackgroundTasks;
use Dashed\DashedCore\Classes\Caching\CacheInvalidator;
use Dashed\DashedCore\Jobs\Concerns\HandlesQueueFailures;

class UpdateProductPricesJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;
    use HandlesQueueFailures;

    public $tries = 5;
    public $timeout = 1200;

    public const TASK_TYPE = 'product-prices';

    /**
     * Vanaf dit aantal groepen meldt een ronde zich in het takenicoon.
     */
    public const TASK_MIN_GROUPS = 10;

    public ProductGroup $productGroup;

    /**
     * De taak in het takenicoon van de ronde waar deze job bij hoort. Met
     * een standaardwaarde, zodat een job die al in de wachtrij stond hem
     * ook heeft.
     */
    public ?int $backgroundTaskId = null;

    /**
     * Create a new job instance.
     */
    public function __construct(ProductGroup $productGroup, ?int $backgroundTaskId = null)
    {
        $this->productGroup = $productGroup;
        $this->backgroundTaskId = $backgroundTaskId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $products = $this->productGroup->products;

        foreach ($products as $product) {
            $product->calculatePrices();

            foreach (DB::table('dashed__product_bundle_products')->where('bundle_product_id', $product->id)->pluck('product_id') as $productId) {
                $bundleParentProduct = Product::find($productId);
                if ($bundleParentProduct) {
                    $bundleParentProduct->calculatePrices();
                }
            }

        }

        // calculatePrices() raakt geen van deze vier velden, dus één keer na de
        // lus geeft dezelfde uitkomst. Een groep zonder producten blijft staan.
        if ($products->isNotEmpty()) {
            $this->productGroup->min_price = $products->min('price');
            $this->productGroup->max_price = $products->max('price');
            $this->productGroup->total_stock = $products->sum('total_stock');
            $this->productGroup->total_purchases = $products->sum('total_purchases');
            $this->productGroup->saveQuietly();

            foreach ($this->productGroup->volumeDiscounts as $volumeDiscount) {
                $volumeDiscount->connectAllProducts();
            }
        }

        if (class_exists(CacheInvalidator::class)) {
            CacheInvalidator::flushSite();
        }

        $this->advanceBackgroundTask();
    }

    /**
     * Na de laatste poging: de groep telt mee als mislukt, anders blijft de
     * taak van de ronde op "Bezig" staan.
     */
    public function failed(Throwable $e): void
    {
        $this->advanceBackgroundTask(failed: true);

        $this->reportFailure($e);
    }

    protected function advanceBackgroundTask(bool $failed = false): void
    {
        BackgroundTasks::advanceBatch(
            $this->backgroundTaskId,
            fn (int $done, int $failedCount): string => $failedCount > 0
                ? __(':aantal productgroepen bijgewerkt, :mislukt mislukt.', ['aantal' => $done, 'mislukt' => $failedCount])
                : __(':aantal productgroepen bijgewerkt.', ['aantal' => $done]),
            $failed,
        );
    }
}
