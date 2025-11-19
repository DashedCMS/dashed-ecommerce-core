<?php

namespace Dashed\DashedEcommerceCore\Commands;

use Dashed\DashedEcommerceCore\Jobs\UpdateProductStockInformationJob;
use Dashed\DashedEcommerceCore\Models\Product;
use Illuminate\Console\Command;
use Dashed\DashedEcommerceCore\Models\ProductGroup;
use Dashed\DashedEcommerceCore\Jobs\UpdateProductInformationJob;

class UpdateProductInformations extends Command
{
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
        $products = Product::get();
        foreach ($products as $product) {
            UpdateProductStockInformationJob::dispatch($product)
                ->onQueue('ecommerce');
        }
    }
}
