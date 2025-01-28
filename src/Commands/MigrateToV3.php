<?php

namespace Dashed\DashedEcommerceCore\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Dashed\DashedCore\Classes\Locales;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Models\ProductGroup;
use Dashed\DashedEcommerceCore\Jobs\UpdateProductInformationJob;

class MigrateToV3 extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dashed:migrate-to-v3';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrate to v3';

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

        $this->info(Product::where('type', 'variable')->where('parent_id', null)->count() + Product::where('type', 'simple')->count());

        $productIdsToDelete = [];
        $productGroupReplacements = [];

        //        ProductGroup::where('id', '!=', 0)->forceDelete();

        foreach (Product::withTrashed()->get() as $product) {
            $this->info('Migrating product ' . $product->id);

            $productGroup = new ProductGroup();

            foreach (Locales::getLocalesArray() as $key => $locale) {
                $productGroup->setTranslation('name', $key, $product->getTranslation('name', $key));
                $productGroup->setTranslation('short_description', $key, $product->getTranslation('short_description', $key));
                $productGroup->setTranslation('description', $key, $product->getTranslation('description', $key));
                $productGroup->setTranslation('content', $key, $product->getTranslation('content', $key));
                $productGroup->setTranslation('search_terms', $key, $product->getTranslation('search_terms', $key));
            }

            $productGroup->site_ids = $product->site_ids ?: [];
            $productGroup->order = $product->order ?: 1;
            $productGroup->only_show_parent_product = $product->only_show_parent_product;

            $connectThisProductToProductGroup = false;
            $connectUnderlyingProductsToProductGroup = false;
            $migrateRelations = false;
            $deleteThisProduct = false;
            $deleteParentProduct = false;

            if ($product->type == 'variable' && $product->parent_id) {
                if (isset($productGroupReplacements[$product->parent_id]) && ProductGroup::where('id', $productGroupReplacements[$product->parent_id])->first()) {
                    $productGroup = ProductGroup::where('id', $productGroupReplacements[$product->parent_id])->first();
                } else {
                    $productGroup->save();
                    $productGroupReplacements[$product->parent_id] = $productGroup->id;
                }
                $connectThisProductToProductGroup = true;
                $migrateRelations = false;
                $deleteParentProduct = true;
                $parentProduct = Product::where('id', $product->parent_id)->withTrashed()->first();
                foreach (Locales::getLocalesArray() as $key => $locale) {
                    $productGroup->setTranslation('slug', $key, $parentProduct->getTranslation('slug', $key));
                }
            } elseif ($product->type == 'variable' && ! $product->parent_id) {
                if (isset($productGroupReplacements[$product->id]) && ProductGroup::where('id', $productGroupReplacements[$product->id])->first()) {
                    $productGroup = ProductGroup::where('id', $productGroupReplacements[$product->id])->first();
                } else {
                    $productGroup->save();
                    $productGroupReplacements[$product->id] = $productGroup->id;
                }
                $connectUnderlyingProductsToProductGroup = true;
                $migrateRelations = true;
                $deleteThisProduct = true;
                $productGroup->only_show_parent_product = $product->only_show_parent_product;
                foreach (Locales::getLocalesArray() as $key => $locale) {
                    $productGroup->setTranslation('slug', $key, $product->getTranslation('slug', $key));
                }
            } elseif ($product->type == 'simple') {
                $productGroup->save();
                $connectThisProductToProductGroup = true;
                $migrateRelations = false;
                $deleteThisProduct = false;
                foreach (Locales::getLocalesArray() as $key => $locale) {
                    $productGroup->setTranslation('slug', $key, $product->getTranslation('slug', $key));
                }
            }


            if ($connectThisProductToProductGroup) {
                $product->product_group_id = $productGroup->id;
                $product->parent_id = null;
                $product->save();
            }

            if ($connectUnderlyingProductsToProductGroup) {
                foreach (Product::where('parent_id', $product->id)->get() as $childProduct) {
                    $childProduct->product_group_id = $productGroup->id;
                    $childProduct->parent_id = null;
                    $childProduct->save();
                }
            }

            DB::table('dashed__product_enabled_filter_options')->where('product_id', $product->id)->update([
                'product_group_id' => $productGroup->id,
                'product_id' => null,
            ]);

            DB::table('dashed__active_product_filter')->where('product_id', $product->id)->update([
                'product_group_id' => $productGroup->id,
                'product_id' => null,
            ]);

            DB::table('dashed__product_category')->where('product_id', $product->id)->update([
                'product_group_id' => $productGroup->id,
                'product_id' => null,
            ]);

            if ($migrateRelations) {
                DB::table('dashed__product_characteristic')->where('product_id', $product->id)->update([
                    'product_group_id' => $productGroup->id,
                    'product_id' => null,
                ]);
                DB::table('dashed__product_crosssell_product')->where('product_id', $product->id)->update([
                    'product_group_id' => $productGroup->id,
                    'product_id' => null,
                ]);
                DB::table('dashed__product_extra_product')->where('product_id', $product->id)->update([
                    'product_group_id' => $productGroup->id,
                    'product_id' => null,
                ]);
                DB::table('dashed__product_extras')->where('product_id', $product->id)->update([
                    'product_group_id' => $productGroup->id,
                    'product_id' => null,
                ]);
                DB::table('dashed__product_suggested_product')->where('product_id', $product->id)->update([
                    'product_group_id' => $productGroup->id,
                    'product_id' => null,
                ]);
                DB::table('dashed__product_tab_product')->where('product_id', $product->id)->update([
                    'product_group_id' => $productGroup->id,
                    'product_id' => null,
                ]);
                DB::table('dashed__product_tabs')->where('product_id', $product->id)->update([
                    'product_group_id' => $productGroup->id,
                    'product_id' => null,
                ]);
            }

            if ($deleteThisProduct) {
                $productIdsToDelete[] = $product->id;
            }
            if ($deleteParentProduct) {
                $productIdsToDelete[] = $product->parent_id;
            }

        }

        Product::whereIn('id', $productIdsToDelete)->delete();

        foreach (ProductGroup::all() as $productGroup) {
            UpdateProductInformationJob::dispatch($productGroup, false)->onQueue('ecommerce');
        }
    }
}
