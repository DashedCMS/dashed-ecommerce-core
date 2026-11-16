<?php

namespace Dashed\DashedEcommerceCore\Commands;

use Illuminate\Console\Command;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Models\ProductGroup;
use Dashed\DashedEcommerceCore\Support\BackgroundTasks;
use Dashed\DashedEcommerceCore\Jobs\UpdateProductInformationJob;
use Dashed\DashedEcommerceCore\Jobs\UpdateProductStockInformationJob;

class UpdateProductInformations extends Command
{
    public const TASK_TYPE = 'product-stock-information';

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dashed:update-product-informations';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update product informations';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        //        $productGroups = ProductGroup::get();
        //        foreach ($productGroups as $productGroup) {
        //            UpdateProductInformationJob::dispatch($productGroup, false)->onQueue('ecommerce');
        //        }
        // De job haalt het product zelf vers op, dus alleen het id is nodig.
        // Eén taak in het takenicoon voor de hele ronde: elke job telt zijn
        // product af.
        $total = Product::query()->count();
        $taskId = $total > 0
            ? BackgroundTasks::startBatch(self::TASK_TYPE, __('Voorraad en verkopen van producten bijwerken'), $total)
            : null;

        Product::query()->select('id')->chunkById(500, function ($products) use ($taskId): void {
            foreach ($products as $product) {
                UpdateProductStockInformationJob::dispatch($product, $taskId)
                    ->onQueue('ecommerce');
            }
        });
    }
}
