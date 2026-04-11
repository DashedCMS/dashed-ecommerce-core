<?php

namespace Dashed\DashedEcommerceCore\Livewire\Frontend\Products;

use Dashed\DashedCore\Classes\Sites;
use Dashed\DashedEcommerceCore\Models\Product;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Livewire\Component;

class Searchbar extends Component
{
    public ?string $search = '';

    public ?Collection $products = null;

    public bool $showSearchbar = false;

    public int $resultLimit = 12;

    public int $minQueryLength = 2;

    public function mount()
    {
        $this->search = is_array(request()->get('search')) ? null : request()->get('search');
    }

    public function getProductsFromSearch()
    {
        $query = trim((string) $this->search);

        if ($query === '' || mb_strlen($query) < $this->minQueryLength) {
            $this->products = null;
            $this->showSearchbar = false;
            $this->dispatch('searchInitiated');

            return;
        }

        $siteId = Sites::getActive();
        $version = Product::searchbarCacheVersion();
        $locale = app()->getLocale();
        $cacheKey = "searchbar.v{$version}.{$siteId}.{$locale}.{$this->resultLimit}." . sha1(mb_strtolower($query));

        $ids = Cache::remember($cacheKey, now()->addDay(), function () use ($query) {
            return Product::search($query)
                ->thisSite()
                ->publicShowableWithIndex()
                ->orderBy('order')
                ->limit($this->resultLimit)
                ->pluck('id')
                ->all();
        });

        if (empty($ids)) {
            $this->products = new Collection();
            $this->showSearchbar = true;
            $this->dispatch('searchInitiated');

            return;
        }

        $this->products = Product::query()
            ->whereIn('id', $ids)
            ->with(['productGroup'])
            ->orderByRaw('FIELD(id, ' . implode(',', $ids) . ')')
            ->get();

        foreach ($this->products as $product) {
            if ($product->productGroup && $product->productGroup->only_show_parent_product) {
                $product->name = $product->parent?->name ?? $product->name;
            }
        }

        $this->showSearchbar = true;
        $this->dispatch('searchInitiated');
    }

    public function updatedSearch()
    {
        if (is_array($this->search)) {
            $this->search = null;

            return false;
        }

        $this->getProductsFromSearch();
    }

    public function render()
    {
        return view(config('dashed-core.site_theme', 'dashed') . '.products.searchbar');
    }
}
