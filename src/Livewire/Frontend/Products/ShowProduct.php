<?php

namespace Qubiqx\QcommerceEcommerceCore\Livewire\Frontend\Products;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Livewire\Component;
use Livewire\WithPagination;
use Qubiqx\QcommerceCore\Models\Customsetting;
use Qubiqx\QcommerceEcommerceCore\Classes\ProductCategories;
use Qubiqx\QcommerceEcommerceCore\Classes\Products;
use Qubiqx\QcommerceEcommerceCore\Models\ProductCategory;

class ShowProduct extends Component
{
    use WithPagination;

    private $products = null;
    private $filters = null;
    public ?ProductCategory $productCategory = null;
    public ?string $pagination = null;
    public ?string $orderBy = null;
    public ?string $order = null;
    public ?string $sortBy = null;
    public ?string $search = '';

    public array $activeFilters = [];
    public array $activeFilterQuery = [];

    public function getQueryString()
    {
        return array_merge([
            'search' => ['except' => ''],
            'sortBy' => ['except' => ''],
            'page' => ['except' => 1],
        ], $this->activeFilterQuery);
    }

    public function mount(?ProductCategory $productCategory = null)
    {
        $this->productCategory = $productCategory;

        $this->pagination = request()->get('pagination');
        $this->orderBy = request()->get('order-by');
        $this->order = request()->get('order');
        $this->sortBy = request()->get('sort-by');
    }

    public function loadProducts()
    {
        if (!$this->products) {

            $activeFilterQuery = [];

            foreach($this->activeFilters as $key => $value) {
                $activeFilterQuery['activeFilters'][$key] = ['except' => ''];
            }

            $this->activeFilterQuery = $activeFilterQuery;

            request()->replace(array_merge([
                'search' => $this->search,
                'sort-by' => $this->sortBy,
                'page' => request()->get('page'),
            ], $this->activeFilters));

            $response = Products::getAll($this->pagination ?: Customsetting::get('product_default_amount_of_products', null, 12), $this->orderBy ?: Customsetting::get('product_default_order_type', null, 'price'), $this->order ?: Customsetting::get('product_default_order_sort', null, 'DESC'), $productCategory->id ?? null);
            $this->products = $response['products'];
            $this->filters = $response['filters'];
        }
    }

    public function render()
    {
        $this->loadProducts();

        return view('qcommerce-ecommerce-core::frontend.products.show-products', [
            'products' => $this->products,
            'filters' => $this->filters,
            'activeFilters' => $this->activeFilters,
        ]);
    }
}
