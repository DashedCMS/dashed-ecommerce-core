<?php

namespace Dashed\DashedEcommerceCore\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Dashed\DashedCore\Classes\Locales;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Models\ProductGroup;
use Dashed\DashedEcommerceCore\Events\Products\ProductInformationUpdatedEvent;

class UpdateProductInformationJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public $tries = 5;
    public $timeout = 1200;

    public ProductGroup $productGroup;
    public bool $updateCategories;

    /**
     * Create a new job instance.
     */
    public function __construct(ProductGroup $productGroup, bool $updateCategories = true)
    {
        $this->productGroup = $productGroup;
        $this->updateCategories = $updateCategories;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $this->productGroup->missing_variations = $this->productGroup->missingVariations();
        $this->productGroup->saveQuietly();

        $loop = 1;

        $hasIndexableProduct = false;

        foreach ($this->productGroup->products as $product) {
            $categories = $this->productGroup->productCategories;
            if ($this->productGroup->sync_categories_to_products) {
                $product->productCategories()->sync($categories);
            }

            foreach ($categories as $category) {
                foreach (DB::table('dashed__product_category_user')->where('product_category_id', $category->id)->get() as $productCategoryUser) {
                    DB::table('dashed__product_user')->updateOrInsert(
                        [
                            'product_id' => $product->id,
                            'user_id' => $productCategoryUser->user_id,
                        ],
                        [
                            'discount_price' => $productCategoryUser->discount_price,
                            'discount_percentage' => $productCategoryUser->discount_percentage,
                        ]
                    );
                }
            }

            $product->calculateStock();
            $product->calculateTotalPurchases();
            $product->calculatePrices();
            if ($this->productGroup->only_show_parent_product && $this->productGroup->firstSelectedProduct && $this->productGroup->firstSelectedProduct->public && $this->productGroup->firstSelectedProduct->id === $product->id && $product->public && $this->productGroup->public) {
                $product->indexable = 1;
                $hasIndexableProduct = true;
            } elseif ((($this->productGroup->only_show_parent_product && ! $hasIndexableProduct && (! $this->productGroup->firstSelectedProduct || ($this->productGroup->firstSelectedProduct && ! $this->productGroup->firstSelectedProduct->public))) || ! $this->productGroup->only_show_parent_product) && $product->public && $this->productGroup->public) {
                $product->indexable = 1;
                $hasIndexableProduct = true;
            } else {
                $product->indexable = 0;
            }
            $product->site_ids = $this->productGroup->site_ids;
            foreach (Locales::getLocalesArray() as $locale => $localeName) {
                $product->setTranslation('search_terms', $locale, $this->productGroup->getTranslation('search_terms', $locale) . ' ' . $product->getTranslation('search_terms', $locale));
            }
            $product->saveQuietly();

            if ($product->is_bundle) {
                $product->calculateDeliveryTime();
            }

            foreach (DB::table('dashed__product_bundle_products')->where('bundle_product_id', $product->id)->pluck('product_id') as $productId) {
                $bundleParentProduct = Product::find($productId);
                if ($bundleParentProduct) {
                    $bundleParentProduct->calculateStock();
                    $bundleParentProduct->calculateTotalPurchases();
                    $bundleParentProduct->calculateDeliveryTime();
                    $bundleParentProduct->calculatePrices();
                }
            }
            Cache::forget('product-showable-characteristics-' . $product->id);
            foreach (Locales::getLocalesArray() as $locale => $localeName) {
                Cache::forget('product-' . $product->id . '-url-' . $locale . '-force-yes');
                Cache::forget('product-' . $product->id . '-url-' . $locale . '-force-no');
            }
            $loop++;
        }

        $this->productGroup->min_price = $this->productGroup->products->min('price');
        $this->productGroup->max_price = $this->productGroup->products->max('price');
        $this->productGroup->total_stock = $this->productGroup->products->sum('total_stock');
        $this->productGroup->total_purchases = $this->productGroup->products->sum('total_purchases');
        $this->productGroup->child_products_count = $this->productGroup->products()->count();

        $productIds = $this->productGroup->products->pluck('id');

        if ($productIds->isEmpty()) {
            $this->productGroup->variation_index = null;
        } else {

            // Alle filter combos per product, in één query
            $rows = DB::table('dashed__product_filter')
                ->select('product_id', 'product_filter_id', 'product_filter_option_id')
                ->whereIn('product_id', $productIds)
                ->orderBy('product_id')
                ->orderBy('product_filter_id')
                ->get()
                ->groupBy('product_id');

            $index = [];

            foreach ($rows as $productId => $filters) {
                // Alleen filters gebruiken die use_for_variations = 1 hebben
                // → haal die 1x op:
                $variationFilterIds = $this->productGroup->activeProductFiltersForVariations
                    ->pluck('id')
                    ->toArray();

                $keyParts = [];

                foreach ($filters as $filterRow) {
                    if (! in_array($filterRow->product_filter_id, $variationFilterIds, true)) {
                        continue;
                    }

                    $keyParts[] = $filterRow->product_filter_id . '-' . $filterRow->product_filter_option_id;
                }

                if (! count($keyParts)) {
                    continue;
                }

                sort($keyParts); // volgorde vastzetten

                $key = implode('|', $keyParts);

                // conflictstrategie: eerste wint, of laatste wint
                $index[$key] = (int)$productId;
            }
            $this->productGroup->variation_index = $index;
        }
        $this->productGroup->saveQuietly();

        Cache::forget('products-for-show-products-');
        Cache::forget('pos_products');
        Cache::forget('product-group-showable-characteristics-' . $this->productGroup->id);
        Cache::forget('product-group-showable-characteristics-without-filters-' . $this->productGroup->id);

        foreach ($this->productGroup->volumeDiscounts as $volumeDiscount) {
            $volumeDiscount->connectAllProducts();
        }

        if ($this->updateCategories) {
            UpdateProductCategoriesInformationJob::dispatch()->onQueue('ecommerce');
        }

        ProductInformationUpdatedEvent::dispatch($this->productGroup);
    }
}
