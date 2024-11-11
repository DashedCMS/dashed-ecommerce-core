<?php

namespace Dashed\DashedEcommerceCore\Classes;

use Illuminate\Support\Facades\Cache;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Models\ProductFilter;
use Dashed\DashedEcommerceCore\Models\ProductCategory;

class Products
{
    public static function get($limit = 4, ?string $orderBy = null, ?string $order = null, $topLevelProductOnly = false)
    {
        //Todo: change these params above into variables to make it flexible
        $orderByRequest = request()->get('sort-by');
        if ($orderByRequest) {
            if ($orderByRequest == 'price-asc') {
                $orderBy = 'price';
                $order = 'ASC';
            } elseif ($orderByRequest == 'price-desc') {
                $orderBy = 'price';
                $order = 'DESC';
            } elseif ($orderByRequest == 'most-sold') {
                $orderBy = 'purchases';
                $order = 'DESC';
            } elseif ($orderByRequest == 'stock') {
                $orderBy = 'stock';
                $order = 'DESC';
            } elseif ($orderByRequest == 'newest') {
                $orderBy = 'created_at';
                $order = 'DESC';
            } elseif ($orderByRequest == 'order') {
                $orderBy = 'order';
                $order = 'ASC';
            }
        }

        if (! $orderBy) {
            $orderBy = Customsetting::get('product_default_order_type', null, 'price');
        }

        if (! $order) {
            $order = Customsetting::get('product_default_order_sort', null, 'DESC');
        }

        $products = Product::search()->publicShowable()->limit($limit)->orderBy($orderBy, $order)->with(['parent']);
        if ($topLevelProductOnly) {
            //publicShowable stops the childProducts from showing
            $products->topLevel();
        }
        $products = $products->get();

        foreach ($products as $product) {
            if ($product->parent && $product->parent->only_show_parent_product) {
                $product->name = $product->parent->name;
            }
        }

        return $products;
    }

    public static function getAll($pagination = 12, $orderBy = 'created_at', $order = 'DESC', $categoryId = null, ?string $search = null)
    {
        $orderByRequest = request()->get('sort-by');
        //        return Cache::tags(['products'])->rememberForever("products-all-$pagination-$orderBy-$order-$categoryId-$orderByRequest-" . request()->get('page', 1), function () use ($pagination, $orderBy, $order, $categoryId, $orderByRequest) {
        if ($orderByRequest) {
            if ($orderByRequest == 'price-asc') {
                $orderBy = 'price';
                $order = 'ASC';
            } elseif ($orderByRequest == 'price-desc') {
                $orderBy = 'price';
                $order = 'DESC';
            } elseif ($orderByRequest == 'most-sold') {
                $orderBy = 'purchases';
                $order = 'DESC';
            } elseif ($orderByRequest == 'stock') {
                $orderBy = 'stock';
                $order = 'DESC';
            } elseif ($orderByRequest == 'newest') {
                $orderBy = 'created_at';
                $order = 'DESC';
            } elseif ($orderByRequest == 'default') {
                $orderBy = 'order';
                $order = 'ASC';
            } else {
                $orderBy = 'order';
                $order = 'ASC';
            }
        } else {
            if ($orderBy == 'default') {
                $orderBy = 'order';
                $order = 'ASC';
            }
        }

        $productFilters = self::getFilters();
        $hasActiveFilters = false;
        foreach ($productFilters as $productFilter) {
            foreach ($productFilter->productFilterOptions as $option) {
                if ($option->checked) {
                    $hasActiveFilters = true;
                }
            }
        }

        $correctProductIds = [];
        if ($categoryId && $category = ProductCategory::with(['products'])->findOrFail($categoryId)) {
            $allProducts = $category->products()->search($search)->thisSite()->publicShowable()->orderBy($orderBy, $order)->with(['productFilters', 'productCategories'])->get();
        } else {
            $allProducts = Product::search($search)->thisSite()->publicShowable()->orderBy($orderBy, $order)->with(['productFilters', 'productCategories'])->get();
        }

        $onlyShowParentIds = [];
        foreach ($allProducts as $product) {
            $productIsValid = true;
            if ($hasActiveFilters) {
                foreach ($productFilters as $productFilter) {
                    $productValidForFilter = false;
                    $filterIsActive = false;
                    foreach ($productFilter->productFilterOptions as $option) {
                        if ($option->checked) {
                            $filterIsActive = true;
                            if (! $productValidForFilter) {
                                if ($product->productFilters()->where('product_filter_id', $productFilter->id)->where('product_filter_option_id', $option->id)->exists()) {
                                    $productValidForFilter = true;
                                }
                            }
                        }
                    }
                    if ($filterIsActive && ! $productValidForFilter) {
                        $productIsValid = false;
                    }
                }
            }

            if ($productIsValid && $product->parent && $product->parent->only_show_parent_product) {
                if (in_array($product->parent->id, $onlyShowParentIds)) {
                    $productIsValid = false;
                } else {
                    $onlyShowParentIds[] = $product->parent->id;
                }
            }

            if ($productIsValid) {
                $correctProductIds[] = $product->id;
            }
        }

        $products = Product::whereIn('id', $correctProductIds)->search($search)->thisSite()->publicShowable()->orderBy($orderBy, $order)->with(['productFilters', 'shippingClasses', 'productCategories', 'parent'])->paginate($pagination)->withQueryString();

        foreach ($products as $product) {
            if ($product->parent && $product->parent->only_show_parent_product) {
                $product->name = $product->parent->name;
            }
        }

        return [
            'products' => $products,
            'filters' => self::getFilters($allProducts->pluck('id')),
        ];
        //        });
    }

    public static function getFilters($products = [])
    {
        $productFilters = ProductFilter::with(['productFilterOptions', 'productFilterOptions.products'])
            ->where('hide_filter_on_overview_page', 0)
            ->orderBy('created_at')
            ->get();

        foreach ($productFilters as $productFilter) {
            $filterHasActiveOptions = false;
            $results = request()->get(str_replace(' ', '_', $productFilter->name));
            foreach ($productFilter->productFilterOptions as $option) {
                if ($results && in_array($option->name, $results)) {
                    $option->checked = true;
                } else {
                    $option->checked = false;
                }
                $option->resultCount = 0;
                if ($products) {
                    $option->resultCount = $option->resultCount + $option->products()->whereIn('product_id', $products)->count();
                    if (! $filterHasActiveOptions && $option->resultCount > 0) {
                        $filterHasActiveOptions = true;
                    }
                }
            }
            $productFilter->hasActiveOptions = $filterHasActiveOptions;
        }

        return $productFilters;
    }

    public static function getHighestPrice()
    {
        $highestPrice = 0;
        $products = Product::thisSite()->publicShowable()->with(['productFilters', 'shippingClasses', 'productCategories'])->get();
        if ($products) {
            foreach ($products as $product) {
                if ($product->currentPrice > $highestPrice) {
                    $highestPrice = $product->currentPrice;
                }
            }
        } else {
            $highestPrice = 100;
        }

        return number_format($highestPrice, 0, '', '');
    }

    public static function getById(int|array $productId)
    {
        if (is_array($productId)) {
            return Product::thisSite()->publicShowable()->where('id', $productId)->with(['productFilters', 'shippingClasses', 'productCategories'])->get();
        } else {
            return Product::thisSite()->publicShowable()->where('id', $productId)->with(['productFilters', 'shippingClasses', 'productCategories'])->first();
        }
    }

    public static function getBySearch($pagination = 12, string $sortBy = 'default', $categoryId = null, ?string $search = null)
    {
        if ($sortBy == 'price-asc') {
            $orderBy = 'price';
            $order = 'ASC';
        } elseif ($sortBy == 'price-desc') {
            $orderBy = 'price';
            $order = 'DESC';
        } elseif ($sortBy == 'most-sold') {
            $orderBy = 'purchases';
            $order = 'DESC';
        } elseif ($sortBy == 'stock') {
            $orderBy = 'stock';
            $order = 'DESC';
        } elseif ($sortBy == 'newest') {
            $orderBy = 'created_at';
            $order = 'DESC';
        } else {
            $orderBy = 'order';
            $order = 'ASC';
        }

        $correctProductIds = [];
        if ($categoryId && $category = ProductCategory::with(['products'])
                ->findOrFail($categoryId)) {
            $allProducts = $category->products()
                ->search($search)
                ->thisSite()
                ->publicShowable()
                ->orderBy($orderBy, $order)
                ->with(['productFilters', 'productCategories'])
                ->get();
        } else {
            $allProducts = Product::search($search)
                ->thisSite()
                ->publicShowable()
                ->orderBy($orderBy, $order)
                ->with(['productFilters', 'productCategories'])
                ->get();
        }

        $onlyShowParentIds = [];
        foreach ($allProducts as $product) {
            $productIsValid = true;

            if ($productIsValid && $product->parent && $product->parent->only_show_parent_product) {
                if (in_array($product->parent->id, $onlyShowParentIds)) {
                    $productIsValid = false;
                } else {
                    $onlyShowParentIds[] = $product->parent->id;
                }
            }

            if ($productIsValid) {
                $correctProductIds[] = $product->id;
            }
        }

        $products = Product::whereIn('id', $correctProductIds)
            ->search($search)
            ->thisSite()
            ->publicShowable()
            ->orderBy($orderBy, $order)
            ->with(['productFilters', 'shippingClasses', 'productCategories', 'parent'])
            ->limit($pagination)
            ->get();

        foreach ($products as $product) {
            if ($product->parent && $product->parent->only_show_parent_product) {
                $product->name = $product->parent->name;
            }
        }

        return $products;
    }

    public static function canFilterOnShortOrColumn(): array
    {
        return [
            'price-asc',
            'price-desc',
            'most-sold',
            'stock',
            'newest',
            'order',
            'default',

            'name',
            'slug',
            'price',
            'purchases',
            'order',
            'in_stock',
            'total_stock',
            'created_at',
        ];
    }

    public static function getAllV2(int $pagination = 12, ?int $page = 1, ?string $orderBy = 'order', ?string $order = 'DESC', ?int $categoryId = null, ?string $search = null, ?array $activeFilters = [], ?array $priceRange = [], ?bool $enableFilters = true)
    {
        if (! in_array($orderBy, self::canFilterOnShortOrColumn())) {
            $orderBy = '';
        }
        if (str($order)->lower() != 'asc' && str($order)->lower() != 'desc') {
            $order = '';
        }

        //Todo: create list of shorts and columns that can be filtered on
        if ($orderBy == 'price-asc') {
            $orderBy = 'price';
            $order = 'ASC';
        } elseif ($orderBy == 'price-desc') {
            $orderBy = 'price';
            $order = 'DESC';
        } elseif ($orderBy == 'most-sold') {
            $orderBy = 'purchases';
            $order = 'DESC';
        } elseif ($orderBy == 'stock') {
            $orderBy = 'stock';
            $order = 'DESC';
        } elseif ($orderBy == 'newest') {
            $orderBy = 'created_at';
            $order = 'DESC';
        } elseif ($orderBy == 'order') {
            $orderBy = 'order';
            $order = 'ASC';
        } else {
            $orderBy = '';
            $order = '';
        }

        if (! $orderBy) {
            $orderBy = Customsetting::get('product_default_order_type', null, 'price');
        }

        if (! $order) {
            $order = Customsetting::get('product_default_order_sort', null, 'DESC');
        }

        if ($enableFilters) {
            $productFilters = self::getFiltersV2([], $activeFilters);
            $hasActiveFilters = false;
            foreach ($productFilters as $productFilter) {
                foreach ($productFilter->productFilterOptions as $option) {
                    if ($option->checked) {
                        $hasActiveFilters = true;
                    }
                }
            }
        }

        $correctProductIds = [];
        $hideProductIds = [];
        if ($categoryId && $category = ProductCategory::findOrFail($categoryId)) {
            $allProducts = $category->products()
                ->search($search)
                ->thisSite()
                ->publicShowable()
                ->orderBy($orderBy, $order)
                ->get();
        } else {
            $allProducts = Product::search($search)
                ->thisSite()
                ->publicShowable()
                ->orderBy($orderBy, $order)
                ->get();
        }

        $onlyShowParentIds = [];
        $retrievedParentProducts = [];

        foreach ($allProducts as $product) {
            $productIsValid = true;
            if ($enableFilters && $hasActiveFilters) {
                foreach ($productFilters as $productFilter) {
                    $productValidForFilter = false;
                    $filterIsActive = false;
                    foreach ($productFilter->productFilterOptions as $option) {
                        if ($option->checked) {
                            $filterIsActive = true;
                            if (! $productValidForFilter) {
                                if ($product->productFilters()->where('product_filter_id', $productFilter->id)->where('product_filter_option_id', $option->id)->exists()) {
                                    $productValidForFilter = true;
                                }
                            }
                        }
                    }
                    if ($filterIsActive && ! $productValidForFilter) {
                        $productIsValid = false;
                    }
                }
            }

            $parentProduct = $product->parent_id ? (collect($retrievedParentProducts)->where('id', $product->parent_id)->first() ?: $product->parent) : null;
            if ($productIsValid && $parentProduct && $parentProduct->only_show_parent_product) {
                if (! in_array($parentProduct, $retrievedParentProducts)) {
                    $retrievedParentProducts[] = $parentProduct;
                }
                if (in_array($parentProduct->id, $onlyShowParentIds)) {
                    $productIsValid = false;
                } else {
                    $onlyShowParentIds[] = $parentProduct->id;
                    $product = $parentProduct;
                }
            }

            if (! $product->parent_id && $product->type == 'variable' && ! $product->only_show_parent_product) {
                $hideProductIds[] = $product->id;
            } elseif (! $product->parent_id && $product->type == 'variable' && ! $product->childProducts()->publicShowable()->count()) {
                $hideProductIds[] = $product->id;
            } elseif ($productIsValid) {
                $correctProductIds[] = $product->id;
            }
        }

        $products = Product::whereIn('id', $correctProductIds)
            ->whereNotIn('id', $hideProductIds)
            ->search($search)
            ->orderBy($orderBy, $order)
            ->orderBy('id')
            ->thisSite()
            ->publicShowable();

        if ($priceRange['min'] ?? false) {
            $products = $products->where('price', '>=', $priceRange['min']);
        }
        if ($priceRange['max'] ?? false) {
            $products = $products->where('price', '<=', $priceRange['max']);
        }

        $products = $products->with(['productFilters', 'shippingClasses', 'productCategories', 'parent'])
            ->paginate($pagination, ['*'], 'page', $page)
            ->withQueryString();

        foreach ($products as $product) {
            if ($product->parent && $product->parent->only_show_parent_product) {
                $product->name = $product->parent->name;
            }
        }

        $minPrice = Product::whereIn('id', $correctProductIds)
            ->search($search)
            ->thisSite()
            ->publicShowable()
            ->min('price');
        $maxPrice = Product::whereIn('id', $correctProductIds)
            ->search($search)
            ->thisSite()
            ->publicShowable()
            ->max('price');

        return [
            'products' => $products,
            'minPrice' => $minPrice,
            'maxPrice' => $maxPrice,
            'filters' => $enableFilters ? self::getFiltersV3($allProducts->pluck('id'), $activeFilters) : [],
        ];
    }

    public static function getFiltersV2($products = [], array $activeFilters = [])
    {
        $productFilters = ProductFilter::with(['productFilterOptions', 'productFilterOptions.products'])
            ->where('hide_filter_on_overview_page', 0)
            ->orderBy('created_at')
            ->get();


        foreach ($productFilters as $productFilter) {
            $filterHasActiveOptions = false;
            $results = $activeFilters[$productFilter->name] ?? [];
            foreach ($productFilter->productFilterOptions as $option) {
                if ($results && array_key_exists($option->name, $results) && $results[$option->name]) {
                    $option->checked = true;
                } else {
                    $option->checked = false;
                }
                $option->resultCount = 0;
                if ($products) {
                    $option->resultCount = $option->resultCount + $option->products()->whereIn('product_id', $products)->count();
                    if (! $filterHasActiveOptions && $option->resultCount > 0) {
                        $filterHasActiveOptions = true;
                    }
                }
            }
            $productFilter->hasActiveOptions = $filterHasActiveOptions;
        }

        return $productFilters;
    }

    public static function getFiltersV3($products = [], array $activeFilters = [])
    {
        $productFilters = ProductFilter::where('hide_filter_on_overview_page', 0)
            ->orderBy('created_at')
            ->get();

        foreach ($productFilters as $productFilter) {
            $filterHasActiveOptions = false;
            $results = $activeFilters[$productFilter->name] ?? [];
            foreach ($productFilter->productFilterOptions as $option) {
                if ($results && array_key_exists($option->name, $results) && $results[$option->name]) {
                    $option->checked = true;
                } else {
                    $option->checked = false;
                }
                $option->resultCount = 0;
                if ($products) {
                    //This is very slow with a lot of products
                    $option->resultCount = $option->resultCount + $option->products()->whereIn('product_id', $products)->count();
                    if (! $filterHasActiveOptions && $option->resultCount > 0) {
                        $filterHasActiveOptions = true;
                    }
                }
            }
            $productFilter->hasActiveOptions = $filterHasActiveOptions;
        }

        return $productFilters;
    }
}
