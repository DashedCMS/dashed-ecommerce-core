<?php

namespace Dashed\DashedEcommerceCore\Classes;

use Illuminate\Support\Facades\DB;
use Dashed\DashedCore\Models\Customsetting;
use Illuminate\Database\Eloquent\Collection;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Models\ProductGroup;
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

        if ($categoryId && $category = ProductCategory::with(['products'])
                ->findOrFail($categoryId)) {
            $products = $category->products()
                ->search($search)
                ->thisSite()
                ->publicShowableWithIndex()
                ->orderBy($orderBy, $order)
                ->limit($pagination)
                ->with(['productFilters', 'productGroup'])
                ->get();
        } else {
            $products = Product::search($search)
                ->thisSite()
                ->publicShowableWithIndex()
                ->orderBy($orderBy, $order)
                ->limit($pagination)
                ->with(['productFilters', 'productGroup'])
                ->get();
        }

        foreach ($products as $product) {
            if ($product->productGroup && $product->productGroup->only_show_parent_product) {
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

    public static function getAll(int $pagination = 12, ?int $page = 1, ?string $orderBy = 'order', ?string $order = 'DESC', ?int $categoryId = null, ?string $search = null, null|array|\Illuminate\Support\Collection $filters = [], ?bool $enableFilters = true, null|array|Collection $products = null, ?array $priceRange = [])
    {
        if (! in_array($orderBy, self::canFilterOnShortOrColumn())) {
            $orderBy = '';
        }
        if (str($order)->lower() != 'asc' && str($order)->lower() != 'desc') {
            $order = '';
        }

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

        if (! $products) {
            if ($categoryId) {
                $productCategory = ProductCategory::with(['products'])->findOrFail($categoryId);
                $products = $productCategory->products()
                    ->search($search)
                    ->publicShowable()
                    ->orderBy($orderBy, $order)
                    ->with(['productFilters', 'productCategories', 'productGroup'])
                    ->get();
            } else {
                $products = Product::search($search)
                    ->publicShowable()
                    ->orderBy($orderBy, $order)
                    ->with(['productFilters', 'productCategories', 'productGroup'])
                    ->get();
            }
        }

        $correctProductIds = [];

        $productFilters = DB::table('dashed__product_filter')
            ->whereIn('product_id', $products->pluck('id' ?? []))
            ->get()
            ->groupBy('product_filter_id');

        $validProductIds = collect($products->pluck('id') ?? []);

        foreach ($filters as $filter) {
            $checkedOptionIds = collect($filter->productFilterOptions)->where('checked', true)->pluck('id');
            $filterIsActive = $checkedOptionIds->isNotEmpty();

            if ($filterIsActive) {
                $productsValidForFilter = $productFilters->get($filter->id, collect())
                    ->whereIn('product_filter_option_id', $checkedOptionIds)
                    ->pluck('product_id');

                $validProductIds = $validProductIds->intersect($productsValidForFilter);

                if ($validProductIds->isEmpty()) {
                    break;
                }
            }
        }

        $correctProductIds = $validProductIds->values()->all();

        $products = Product::whereIn('id', $correctProductIds)
            ->search($search)
            ->orderBy($orderBy, $order)
            ->orderBy('id');

        if (! empty($priceRange['min'])) {
            $products = $products->where('price', '>=', $priceRange['min']);
        }
        if (! empty($priceRange['max'])) {
            $products = $products->where('price', '<=', $priceRange['max']);
        }

        $products = $products->with(['productFilters', 'productCategories', 'productGroup'])
            ->paginate($pagination, ['*'], 'page', $page)
            ->withQueryString();

        foreach ($products as $product) {
            if ($product->productGroup->only_show_parent_product) {
                $product->name = $product->productGroup->name;
            }
        }

        $minPrice = $products->min('price');
        $maxPrice = $products->max('price');

        return [
            'products' => $products,
            'minPrice' => $minPrice,
            'maxPrice' => $maxPrice,
        ];
    }

    public static function getRecentlyViewed(int $limit = 12, ProductGroup $productGroup = null)
    {
        $recentlyViewedProductGroups = session('recentlyViewedProducts', []);
        if ($productGroup && in_array($productGroup->id, $recentlyViewedProductGroups)) {
            $key = array_search($productGroup->id, $recentlyViewedProductGroups);
            unset($recentlyViewedProductGroups[$key]);
        }

        $products = Product::whereIn('product_group_id', $recentlyViewedProductGroups)
            ->publicShowable()
//            ->orderBy($orderBy, $order)
            ->orderBy('id')
            ->with(['productFilters', 'productCategories', 'productGroup'])
            ->limit($limit)
            ->get();

        foreach ($products as $product) {
            if ($product->productGroup->only_show_parent_product) {
                $product->name = $product->productGroup->name;
            }
        }

        return $products;
    }
}
