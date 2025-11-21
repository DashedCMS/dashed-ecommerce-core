<?php

namespace Dashed\DashedEcommerceCore\Classes;

use Illuminate\Support\Facades\DB;
use Dashed\DashedCore\Models\Customsetting;
use Illuminate\Database\Eloquent\Collection;
use Dashed\DashedEcommerceCore\Models\Product;
use Illuminate\Pagination\LengthAwarePaginator;
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

        $products = Product::search()
            ->publicShowable()
            ->limit($limit)
            ->orderBy($orderBy, $order)
            ->with(['parent']);

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

        if ($categoryId && $category = ProductCategory::with(['products'])->findOrFail($categoryId)) {
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

    public static function getAll(
        int                                       $pagination = 12,
        ?int                                      $page = 1,
        ?string                                   $orderBy = 'order',
        ?string                                   $order = 'DESC',
        ?int                                      $categoryId = null,
        ?string                                   $search = null,
        null|array|\Illuminate\Support\Collection $filters = [],
        ?bool                                     $enableFilters = true,
        null|array|Collection                     $products = null,
        ?array                                    $priceRange = []
    ) {
        if (! in_array($orderBy, self::canFilterOnShortOrColumn(), true)) {
            $orderBy = '';
        }

        if (strtolower((string) $order) != 'asc' && strtolower((string) $order) != 'desc') {
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
        } elseif ($orderBy == 'purchases') {
            $orderBy = 'total_purchases';
            $order = 'DESC';
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

        $productFilters = DB::table('dashed__product_filter')
            ->whereIn('product_id', $products->pluck('id'))
            ->get()
            ->groupBy('product_filter_id');

        $validProductIds = collect($products->pluck('id'));

        foreach ($filters as $filter) {
            $checkedOptionIds = collect($filter->productFilterOptions ?? [])
                ->where('checked', true)
                ->pluck('id');

            if ($checkedOptionIds->isEmpty()) {
                continue;
            }

            $productsValidForFilter = $productFilters->get($filter->id, collect())
                ->whereIn('product_filter_option_id', $checkedOptionIds)
                ->pluck('product_id');

            $validProductIds = $validProductIds->intersect($productsValidForFilter);

            if ($validProductIds->isEmpty()) {
                break;
            }
        }

        $products = $products
            ->whereIn('id', $validProductIds->values())
            ->values();

        // 4) Prijsfilter lokaal toepassen
        if (! empty($priceRange['min'])) {
            $products = $products->filter(fn ($p) => $p->price >= $priceRange['min']);
        }
        if (! empty($priceRange['max'])) {
            $products = $products->filter(fn ($p) => $p->price <= $priceRange['max']);
        }

        // 5) Sorteren + search (hybride)
        if ($search) {
            $needle = trim(mb_strtolower($search));
            $orderLower = strtolower($order);

            // Translatable kolommen voor Product
            $productColumns = collect((new Product())->getTranslatableAttributes())
                ->reject(fn (string $attr) => method_exists(Product::class, $attr))
                ->values()
                ->all();

            $productColumnCount = count($productColumns);
            $topWeight = $productColumnCount + 10;

            // Translatable kolommen voor ProductGroup (aparte set!)
            $groupColumns = collect((new ProductGroup())->getTranslatableAttributes() ?? [])
                ->reject(fn (string $attr) => method_exists(ProductGroup::class, $attr))
                ->values()
                ->all();

            $groupColumnCount = count($groupColumns);

            // 1) Per product een search_score berekenen
            $products = $products->map(function (Product $product) use (
                $search,
                $needle,
                $productColumns,
                $productColumnCount,
                $groupColumns,
                $groupColumnCount,
                $topWeight
            ) {
                $score = 0.0;

                // Exacte match op sku/ean/article_code → grote boost
                foreach (['sku', 'ean', 'article_code'] as $exactField) {
                    $value = (string) ($product->{$exactField} ?? '');
                    if ($value !== '' && strcasecmp($value, $search) === 0) {
                        $score += $topWeight;
                    }
                }

                // Product translatable kolommen
                foreach ($productColumns as $idx => $col) {
                    $weight = $productColumnCount - $idx;

                    $value = ($product->getTranslation($col, app()->getLocale()) ?? '');
                    if (is_array($value)) {
                        $value = json_encode($value);
                    }
                    $valueLower = mb_strtolower($value);

                    if ($valueLower !== '' && str_contains($valueLower, $needle)) {
                        $localScore = $weight;

                        similar_text($valueLower, $needle, $percent);
                        $localScore += ($percent / 100) * $weight;

                        $score += $localScore;
                    }
                }

                // ProductGroup translatable kolommen, lagere weight
                if ($product->relationLoaded('productGroup') && $product->productGroup) {
                    foreach ($groupColumns as $idx => $col) {
                        $weight = max(1, ($groupColumnCount - $idx)) * 0.8;

                        $groupValue = ($product->productGroup->getTranslation($col, app()->getLocale()) ?? '');
                        if (is_array($groupValue)) {
                            $groupValue = json_encode($groupValue);
                        }

                        $groupValueLower = mb_strtolower($groupValue);

                        if ($groupValueLower !== '' && str_contains($groupValueLower, $needle)) {
                            $localScore = $weight;

                            similar_text($groupValueLower, $needle, $percentGroup);
                            $localScore += ($percentGroup / 100) * $weight;

                            $score += $localScore;
                        }
                    }
                }

                // Clone om gekke referentie-dingen te voorkomen
                $clone = clone $product;
                $clone->search_score = $score;

                return $clone;
            });

            // 2) Eerst sorteren op gekozen orderBy/order (price/newest/whatever)
            if ($orderBy) {
                $products = $products->sortBy(
                    $orderBy,
                    SORT_REGULAR,
                    $orderLower === 'desc'
                );
            }

            // 3) Daarna sorteren op search_score desc (meest relevant eerst)
            $products = $products
                ->sortByDesc('search_score')
                ->values();
        } else {
            // Geen search: gewoon sorteren op gekozen kolom
            if ($orderBy) {
                $products = $products
                    ->sortBy($orderBy, SORT_REGULAR, strtolower($order) === 'desc')
                    ->values();
            }
        }

        // 👉 Hier: duplicates hard afvangen op id
        $products = $products->unique('id')->values();

        // 6) Min/max prijs over volledige (gefilterde) set
        $minPrice = $products->min('price');
        $maxPrice = $products->max('price');

        // 7) Pagineren op collection (geen extra query)
        $page = $page ?: 1;
        $total = $products->count();
        $items = $products->forPage($page, $pagination)->values();

        $paginator = new LengthAwarePaginator(
            $items,
            $total,
            $pagination,
            $page,
            [
                'path'  => request()->url(),
                'query' => request()->query(),
            ]
        );

        return [
            'products' => $paginator,
            'minPrice' => $minPrice,
            'maxPrice' => $maxPrice,
        ];
    }

    public static function getRecentlyViewed(int $limit = 12, ProductGroup $productGroup = null)
    {
        // 1. Basis: recently viewed product-groups uit de sessie
        $recentlyViewedGroupIds = session('recentlyViewedProducts', []);

        // Uniek en opnieuw indexeren
        $recentlyViewedGroupIds = array_values(array_unique($recentlyViewedGroupIds));

        // Huidige productgroep nooit tonen in de lijst
        if ($productGroup) {
            $recentlyViewedGroupIds = array_values(array_diff($recentlyViewedGroupIds, [$productGroup->id]));
        }

        $recentlyViewedGroupIds = array_reverse($recentlyViewedGroupIds);

        $result = collect();
        $usedGroupIds = [];
        $usedProductIds = [];

        // Helper om achteraf de namen te fixen voor parent-only producten
        $applyParentNames = function (\Illuminate\Support\Collection $products) {
            foreach ($products as $product) {
                if ($product->productGroup && $product->productGroup->only_show_parent_product) {
                    $product->name = $product->productGroup->name;
                }
            }

            return $products;
        };

        /*
         * STAP 1: Recently viewed producten ophalen (maximaal 1 per productgroep)
         */
        if (! empty($recentlyViewedGroupIds)) {
            $recentProducts = Product::whereIn('product_group_id', $recentlyViewedGroupIds)
                ->publicShowable()
                ->with(['productFilters', 'productCategories', 'productGroup'])
                ->get();

            // Sorteer op volgorde van recently viewed (eerste in array = meest recent)
            $recentProducts = $recentProducts->sortBy(function ($product) use ($recentlyViewedGroupIds) {
                return array_search($product->product_group_id, $recentlyViewedGroupIds, true);
            });

            foreach ($recentProducts as $product) {
                if ($result->count() >= $limit) {
                    break;
                }

                // Skip als we al een product uit deze productgroep hebben
                if (in_array($product->product_group_id, $usedGroupIds, true)) {
                    continue;
                }

                $result->push($product);
                $usedGroupIds[] = $product->product_group_id;
                $usedProductIds[] = $product->id;
            }
        }

        /*
         * STAP 2: Aanvullen met producten uit dezelfde categorieën
         *
         * - Als een $productGroup is meegegeven: categorieën van die groep gebruiken
         * - Anders: categorieën van de al gevonden producten gebruiken
         */
        if ($result->count() < $limit) {
            $categoryIds = collect();

            if ($productGroup) {
                // Pak alle producten in deze productgroep en verzamel hun categorieën
                $groupProducts = Product::where('product_group_id', $productGroup->id)
                    ->with('productCategories:id')
                    ->get();

                $categoryIds = $groupProducts->flatMap(function ($product) {
                    return $product->productCategories->pluck('id');
                });
            } else {
                // Categorieën op basis van de al gekozen recently viewed producten
                $categoryIds = $result->flatMap(function ($product) {
                    return $product->productCategories->pluck('id');
                });
            }

            $categoryIds = $categoryIds->unique()->values();

            if ($categoryIds->isNotEmpty()) {
                $table = (new ProductCategory())->getTable();

                $sameCategoryProducts = Product::publicShowable()
                    ->whereNotIn('product_group_id', $usedGroupIds)
                    ->whereHas('productCategories', function ($q) use ($categoryIds, $table) {
                        $q->whereIn("$table.id", $categoryIds);
                    })
                    ->with(['productFilters', 'productCategories', 'productGroup'])
                    ->inRandomOrder()
                    ->limit($limit * 3)
                    ->get();

                foreach ($sameCategoryProducts as $product) {
                    if ($result->count() >= $limit) {
                        break;
                    }

                    if (in_array($product->product_group_id, $usedGroupIds, true)) {
                        continue;
                    }

                    $result->push($product);
                    $usedGroupIds[] = $product->product_group_id;
                    $usedProductIds[] = $product->id;
                }
            }
        }

        /*
         * STAP 3: Fallback – random producten (als er nog niet genoeg zijn)
         */
        if ($result->count() < $limit) {
            $fallbackProducts = Product::publicShowable()
                ->whereNotIn('product_group_id', $usedGroupIds)
                ->with(['productFilters', 'productCategories', 'productGroup'])
                ->inRandomOrder()
                ->limit($limit * 3)
                ->get();

            foreach ($fallbackProducts as $product) {
                if ($result->count() >= $limit) {
                    break;
                }

                if (in_array($product->product_group_id, $usedGroupIds, true)) {
                    continue;
                }

                $result->push($product);
                $usedGroupIds[] = $product->product_group_id;
                $usedProductIds[] = $product->id;
            }
        }

        // Namen overschrijven als de productgroep alleen de parent wil tonen
        $result = $applyParentNames($result);

        // Zorg dat we exact $limit items teruggeven, netjes ge-reindexed
        return $result->take($limit)->values();
    }
}
