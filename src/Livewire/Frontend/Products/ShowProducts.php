<?php

namespace Dashed\DashedEcommerceCore\Livewire\Frontend\Products;

use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Support\Facades\DB;
use Dashed\DashedCore\Classes\Sites;
use Illuminate\Support\Facades\Cache;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Classes\Products;
use Dashed\DashedEcommerceCore\Models\ProductCategory;

class ShowProducts extends Component
{
    use WithPagination;

    private $products = null;
    private $allProducts = null;
    private $filters = [];
    public ?ProductCategory $productCategory = null;
    public ?string $pagination = null;
    public ?string $orderBy = null;
    public ?string $order = null;
    public ?string $sortBy = null;

    #[Url(except: '')]
    public $search = '';

    public array $priceSlider = [];
    public array $defaultSliderOptions = [];
    #[Url]
    public int $page = 1;

    public array $activeFilters = [];
    public array $activeFilterQuery = [];
    public array $usableFilters = [];
    public bool $enableFilters = true;
    public bool $hasActiveFilters = false;

    public $event = [];

    protected function queryString()
    {
        return array_merge([
            'search' => ['except' => ''],
            'sortBy' => ['except' => ''],
            'page' => ['except' => 1],
            'activeFilters' => $this->activeFilters,
        ], []);
    }

    public function mount($productCategory = null, $enableFilters = true)
    {
        $this->productCategory = $productCategory;

        $this->pagination = request()->get('pagination', Customsetting::get('product_default_amount_of_products', null, 12));
        $this->sortBy = request()->get('sort-by', request()->get('sortBy', Customsetting::get('product_default_order_type', null, 'price')));
        $this->order = request()->get('order', Customsetting::get('product_default_order_sort', null, 'DESC'));
        $this->search = request()->get('search');
        $this->enableFilters = $enableFilters;

        $activeFilters = request()->get('activeFilters', []);
        foreach ($activeFilters as $filterKey => $activeFilter) {
            foreach ($activeFilter as $optionKey => $value) {
                if (!$value || $value === 'false') {
                    unset($activeFilters[$filterKey][$optionKey]);
                } else {
                    $activeFilters[$filterKey][$optionKey] = true;
                }
            }
        }
        $this->activeFilters = $activeFilters;


        $this->loadProducts(true);
    }

    public function updated()
    {
        $this->page = 1;
        $this->loadProducts();
    }

    public function updatedPage($page)
    {
        $this->page = $page;
        $this->loadProducts();
    }

    public function loadProducts(bool $isMount = false)
    {
        $activeFilterQuery = [];
        $usableFilters = [];
        foreach ($this->activeFilters as $filterKey => $filterValues) {
            foreach ($filterValues as $valueKey => $valueActivated) {
                if ($valueActivated) {
                    $activeFilterQuery['activeFilters'][$valueKey] = ['except' => ''];
                }
            }
        }

        $this->activeFilterQuery = $activeFilterQuery;

        request()->replace(array_merge([
            'search' => $this->search,
            'sortBy' => $this->sortBy,
            'page' => request()->get('page'),
            'activeFilters' => $this->activeFilters,
        ], []));

//        if ($isMount) {
            $this->getProducts();
            if ($this->enableFilters) {
                $this->getFilters();
            }
//        }

        $response = Products::getAll($this->pagination, $this->page, $this->sortBy, $this->order, $this->productCategory->id ?? null, $this->search, $this->filters, $this->enableFilters, $this->allProducts, $this->priceSlider);
        $this->products = $response['products'];

        $this->defaultSliderOptions = [
            'start' => [
                (float)$response['minPrice'] ?? 0,
                (float)$response['maxPrice'] ?? 1000,
            ],
            'range' => [
                'min' => [(float)$response['minPrice'] ?? 0],
                'max' => [(float)$response['maxPrice'] ?? 1000],
            ],
            'connect' => true,
            'behaviour' => 'tap-drag',
            'tooltips' => true,
            'step' => 1,
        ];
    }

    public function getFilters(): void
    {
        $filtersWithCounts = DB::table('dashed__product_filters as pf')
            ->join('dashed__product_filter_options as pfo', 'pf.id', '=', 'pfo.product_filter_id')
            ->leftJoin('dashed__product_filter as dpf', 'pfo.id', '=', 'dpf.product_filter_option_id')
            ->select(
                'pf.id as filter_id',
                'pf.name as filter_name',
                'pf.hide_filter_on_overview_page',
                'pf.created_at',
                'pfo.order as order',
                'pfo.id as option_id',
                'pfo.name as option_name',
                DB::raw('COUNT(dpf.product_id) as option_count')
            )
            ->where('pf.hide_filter_on_overview_page', 0)
            ->groupBy('pf.id', 'pfo.id')
            ->orderBy('pfo.order')
            ->get()
            ->groupBy('filter_id');

        $activeFilters = $this->activeFilters;

        $productFilters = $filtersWithCounts->map(function ($filterOptions, $filterId) use ($activeFilters) {
            $filterName = json_decode($filterOptions->first()->filter_name, true)[app()->getLocale()] ?? 'Onbekend';

            $mappedOptions = $filterOptions->map(function ($option) use ($filterName, $activeFilters) {
                $option->option_name = json_decode($option->option_name, true)[app()->getLocale()] ?? 'Onbekend';

                return (object)[
                    'id' => $option->option_id,
                    'name' => $option->option_name,
                    'checked' => $activeFilters[$filterName][$option->option_name] ?? false,
                ];
            });

            return (object)[
                'id' => $filterId,
                'name' => $filterName,
                'productFilterOptions' => $mappedOptions,
            ];
        });


        $activeOptions = DB::table('dashed__product_filter')
            ->whereIn('product_id', $this->allProducts->pluck('id'))
            ->pluck('product_filter_option_id')
            ->toArray();

        $productFilters = $productFilters->filter(function ($productFilter) use ($activeOptions) {
            $productFilter->productFilterOptions = $productFilter->productFilterOptions->filter(function ($option) use ($activeOptions) {
                $option->active = in_array($option->id, $activeOptions);

                return $option->active;
            });

            return $productFilter->productFilterOptions->isNotEmpty();
        });

        $this->filters = $productFilters;
    }

    public function getProducts(): void
    {
        $productCategory = $this->productCategory;
        $locale = app()->getLocale();
        $siteId = Sites::getActive() ?? 'default';

        $cacheKey = 'products-for-show-products-'
            . ($productCategory->id ?? 'all')
            . '-site-' . $siteId
            . '-locale-' . $locale;

        //        $products = Cache::rememberForever($cacheKey, function () use ($productCategory) {
        $query = Product::query()
            ->publicShowableWithIndex()
            ->with([
                'productFilters',
                'productFilters.productFilterOptions',
            ]);

        if ($productCategory) {
            $query = $productCategory
                ->products()
                ->publicShowableWithIndex()
                ->with([
                    'productFilters',
                    'productFilters.productFilterOptions',
                ]);
        }

        $products = $query->get();
        //            return $query->get();
        //        });

        $this->allProducts = $products;
    }

    public function setSortByValue($value)
    {
        $this->sortBy = $value;
        $this->loadProducts();
    }

    public function removeFilter($filterKey, $optionKey)
    {
        $this->activeFilters[$filterKey][$optionKey] = false;
        $this->loadProducts();
    }

    public function updatedSearch()
    {
        $this->dispatch('searchInitiated');
    }

    public function render()
    {
        return view(config('dashed-core.site_theme') . '.products.show-products', [
            'products' => $this->products,
            'filters' => $this->filters,
            'activeFilters' => $this->activeFilters,
        ]);
    }
}
