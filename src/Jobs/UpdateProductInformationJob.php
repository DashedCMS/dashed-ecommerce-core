<?php

namespace Dashed\DashedEcommerceCore\Jobs;

use Dashed\DashedEcommerceCore\Resources\ProductFeedResource;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Dashed\DashedCore\Classes\Locales;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
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

    // Spreid retries zodat je niet meteen weer dezelfde deadlock pakt
    public $backoff = [5, 15, 45, 90, 180];

    public ProductGroup $productGroup;
    public bool $updateCategories;

    public function __construct(ProductGroup $productGroup, bool $updateCategories = true)
    {
        $this->productGroup = $productGroup;
        $this->updateCategories = $updateCategories;
    }

    /**
     * Zorg dat dezelfde productGroup nooit parallel ge-updated wordt.
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('update-product-group-'.$this->productGroup->id))
                ->expireAfter($this->timeout) // TTL van de lock
                ->releaseAfter(10),           // als lock bestaat: wacht 10 sec en probeer later opnieuw
        ];
    }

    public function handle(): void
    {
        // Refresh om stale relations te vermijden (zeker in queued jobs)
        $this->productGroup->refresh();

        // Klein writeje upfront (mag, maar doen we ook in retryable transaction)
        DB::transaction(function () {
            $this->productGroup->missing_variations = $this->productGroup->missingVariations();
            $this->productGroup->saveQuietly();
        }, 5);

        $hasIndexableProduct = false;

        // Haal dit 1x op (scheelt veel herhaalwerk)
        $locales = Locales::getLocalesArray();

        // Categories 1x laden
        $categories = $this->productGroup->productCategories;

        // Variation filters 1x laden
        $variationFilterIds = $this->productGroup->activeProductFiltersForVariations
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->toArray();

        // Products 1x laden
        $products = $this->productGroup->products;

        // Preload category-user regels in 1 query (i.p.v. per category)
        $categoryUsers = collect();
        if ($categories->count()) {
            $categoryUsers = DB::table('dashed__product_category_user')
                ->whereIn('product_category_id', $categories->pluck('id'))
                ->get(['user_id', 'discount_price', 'discount_percentage'])
                ->unique('user_id')
                ->values();
        }

        foreach ($products as $product) {
            DB::transaction(function () use (
                $product,
                $categories,
                $categoryUsers,
                $locales,
                &$hasIndexableProduct
            ) {
                // 1) Sync categories (alleen als nodig)
                if ($this->productGroup->sync_categories_to_products) {
                    $product->productCategories()->sync($categories);
                }

                // 2) Pivot product_user updates in 1 upsert (veel minder lock-gedoe)
                if ($categoryUsers->count()) {
                    $rows = $categoryUsers->map(fn ($cu) => [
                        'product_id' => $product->id,
                        'user_id' => $cu->user_id,
                        'discount_price' => $cu->discount_price,
                        'discount_percentage' => $cu->discount_percentage,
                    ])->all();

                    DB::table('dashed__product_user')->upsert(
                        $rows,
                        ['product_id', 'user_id'],
                        ['discount_price', 'discount_percentage']
                    );
                }

                // 3) Calculations (hier gebeuren waarschijnlijk de price writes die deadlocken)
                $product->calculateStock();
                $product->calculateTotalPurchases();
                $product->calculatePrices();

                // 4) Indexable logic (zelfde als jouw code, alleen iets leesbaarder)
                $shouldBeIndexable = false;

                if($this->productGroup->showable_in_index){
                    if (
                        $this->productGroup->only_show_parent_product
                        && $this->productGroup->firstSelectedProduct
                        && $this->productGroup->firstSelectedProduct->public
                        && $this->productGroup->firstSelectedProduct->id === $product->id
                        && $product->public
                        && $this->productGroup->public
                    ) {
                        $shouldBeIndexable = true;
                    } elseif (
                        (
                            (
                                $this->productGroup->only_show_parent_product
                                && ! $hasIndexableProduct
                                && (
                                    ! $this->productGroup->firstSelectedProduct
                                    || (
                                        $this->productGroup->firstSelectedProduct
                                        && ! $this->productGroup->firstSelectedProduct->public
                                    )
                                )
                            )
                            || ! $this->productGroup->only_show_parent_product
                        )
                        && $product->public
                        && $this->productGroup->public
                    ) {
                        $shouldBeIndexable = true;
                    }

                    $product->indexable = $shouldBeIndexable ? 1 : 0;
                }else{
                    $product->indexable = 0;
                }
                if ($shouldBeIndexable) {
                    $hasIndexableProduct = true;
                }

                $product->site_ids = $this->productGroup->site_ids;

                foreach ($locales as $locale => $localeName) {
                    $product->setTranslation(
                        'search_terms',
                        $locale,
                        trim($this->productGroup->getTranslation('search_terms', $locale) . ' ' . $product->getTranslation('search_terms', $locale))
                    );
                }

                $product->saveQuietly();

                // Bundles
                if ($product->is_bundle) {
                    $product->calculateDeliveryTime();
                }

                // Update bundle parents (kan ook locken: keep transaction small!)
            }, 5);

            // Bundle parents liever buiten de transaction (keep locks short)
            foreach (DB::table('dashed__product_bundle_products')->where('bundle_product_id', $product->id)->pluck('product_id') as $productId) {
                $bundleParentProduct = Product::find($productId);
                if ($bundleParentProduct) {
                    DB::transaction(function () use ($bundleParentProduct) {
                        $bundleParentProduct->calculateStock();
                        $bundleParentProduct->calculateTotalPurchases();
                        $bundleParentProduct->calculateDeliveryTime();
                        $bundleParentProduct->calculatePrices();
                    }, 5);
                }
            }

            // Cache clears
            Cache::forget('product-showable-characteristics-' . $product->id);
            foreach ($locales as $locale => $localeName) {
                Cache::forget('product-' . $product->id . '-url-' . $locale . '-force-yes');
                Cache::forget('product-' . $product->id . '-url-' . $locale . '-force-no');
            }

            $originalLocale = App::getLocale();
            foreach (Locales::getLocalesArray() as $locale => $name) {
                App::setLocale($locale);

                $payload = (new ProductFeedResource($product))->toArray(null);
                $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

                DB::table('dashed__product_feed_data')->upsert(
                    [[
                        'product_id' => $product->id,
                        'locale' => $locale,
                        'payload' => $json,
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]],
                    ['product_id', 'locale'],
                    ['payload', 'updated_at']
                );
            }
            App::setLocale($originalLocale);
        }

        // Aggregate fields + variation index + filter cleanup
        DB::transaction(function () use ($products, $variationFilterIds) {
            $this->productGroup->refresh();

            $this->productGroup->min_price = $products->min('price');
            $this->productGroup->max_price = $products->max('price');
            $this->productGroup->total_stock = $products->sum('total_stock');
            $this->productGroup->total_purchases = $products->sum('total_purchases');
            $this->productGroup->child_products_count = $this->productGroup->products()->count();

            $productIds = $products->pluck('id');

            // Filters cleanup (deletes kunnen zwaar zijn)
            $activeFilters = $this->productGroup->activeProductFilters()->with(['productFilterOptions'])->get();

            foreach ($activeFilters as $filter) {
                $allProductFilterOptions = $filter->productFilterOptions->pluck('id')->toArray();

                $enabledProductFilterOptions = DB::table('dashed__product_enabled_filter_options')
                    ->where('product_filter_id', $filter->id)
                    ->where('product_group_id', $this->productGroup->id)
                    ->pluck('product_filter_option_id')
                    ->toArray();

                DB::table('dashed__product_filter')
                    ->whereIn('product_filter_option_id', $allProductFilterOptions)
                    ->whereNotIn('product_filter_option_id', $enabledProductFilterOptions)
                    ->whereIn('product_id', $productIds)
                    ->delete();
            }

            // Variation index
            if ($productIds->isEmpty()) {
                $this->productGroup->variation_index = null;
            } else {
                $rows = DB::table('dashed__product_filter')
                    ->select('product_id', 'product_filter_id', 'product_filter_option_id')
                    ->whereIn('product_id', $productIds)
                    ->orderBy('product_id')
                    ->orderBy('product_filter_id')
                    ->get()
                    ->groupBy('product_id');

                $index = [];

                foreach ($rows as $productId => $filters) {
                    $keyParts = [];

                    foreach ($filters as $filterRow) {
                        if (! in_array((int) $filterRow->product_filter_id, $variationFilterIds, true)) {
                            continue;
                        }

                        $keyParts[] = (int) $filterRow->product_filter_id . '-' . (int) $filterRow->product_filter_option_id;
                    }

                    if (! $keyParts) {
                        continue;
                    }

                    sort($keyParts);

                    $key = implode('|', $keyParts);
                    $index[$key] = (int) $productId;
                }

                $this->productGroup->variation_index = $index;
            }

            $this->productGroup->saveQuietly();
        }, 5);

        // Cache clears (post-commit)
        Cache::forget('products-for-show-products-');
        Cache::forget('pos_products');
        Cache::forget('product-group-showable-characteristics-' . $this->productGroup->id);
        Cache::forget('product-group-showable-characteristics-without-filters-' . $this->productGroup->id);

        // Volume discounts
        foreach ($this->productGroup->volumeDiscounts as $volumeDiscount) {
            // eventueel ook queued maken als dit heavy is
            $volumeDiscount->connectAllProducts();
        }

        if ($this->updateCategories) {
            UpdateProductCategoriesInformationJob::dispatch()->onQueue('ecommerce');
        }

        ProductInformationUpdatedEvent::dispatch($this->productGroup);
    }
}
