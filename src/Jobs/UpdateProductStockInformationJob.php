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
use Dashed\DashedCore\Classes\Caching\CacheInvalidator;
use Dashed\DashedEcommerceCore\Support\BackgroundTasks;
use Dashed\DashedCore\Jobs\Concerns\HandlesQueueFailures;

class UpdateProductStockInformationJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;
    use HandlesQueueFailures;

    public $tries = 5;
    public $timeout = 1200;

    public Product $product;

    /**
     * De taak in het takenicoon van de nachtelijke ronde waar deze job bij
     * hoort. Met een standaardwaarde, zodat een job die al in de wachtrij
     * stond hem ook heeft.
     */
    public ?int $backgroundTaskId = null;

    /**
     * Create a new job instance.
     */
    public function __construct(Product $product, ?int $backgroundTaskId = null)
    {
        $this->product = $product;
        $this->backgroundTaskId = $backgroundTaskId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $this->product->calculateStock();
        $this->product->calculateTotalPurchases();

        if ($this->product->is_bundle) {
            $this->product->calculateDeliveryTime();
        }

        foreach (DB::table('dashed__product_bundle_products')->where('bundle_product_id', $this->product->id)->pluck('product_id') as $productId) {
            $bundleParentProduct = Product::find($productId);
            if ($bundleParentProduct) {
                $bundleParentProduct->calculateStock();
                $bundleParentProduct->calculateTotalPurchases();
                $bundleParentProduct->calculateDeliveryTime();
            }
        }

        // BUG-008: product_group_id is nullable (een losstaand product zonder
        // groep); zonder deze guard crasht elke voorraadupdate van zo'n
        // product, bijvoorbeeld de afboeking na een handmatige bestelling.
        if ($this->product->productGroup) {
            $this->product->productGroup->total_stock = $this->product->productGroup->products->sum('total_stock');
            $this->product->productGroup->total_purchases = $this->product->productGroup->products->sum('total_purchases');
            $this->product->productGroup->saveQuietly();
        }

        if (class_exists(CacheInvalidator::class)) {
            CacheInvalidator::flushSite();
        }

        $this->advanceBackgroundTask();
    }

    /**
     * Na de laatste poging: het product telt mee als mislukt, anders blijft
     * de taak van de ronde op "Bezig" staan.
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
                ? __(':aantal producten bijgewerkt, :mislukt mislukt.', ['aantal' => $done, 'mislukt' => $failedCount])
                : __(':aantal producten bijgewerkt.', ['aantal' => $done]),
            $failed,
        );
    }
}
