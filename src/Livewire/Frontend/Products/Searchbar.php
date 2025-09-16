<?php

namespace Dashed\DashedEcommerceCore\Livewire\Frontend\Products;

use Livewire\Component;
use Illuminate\Database\Eloquent\Collection;
use Dashed\DashedEcommerceCore\Classes\Products;

class Searchbar extends Component
{
    public ?string $search = '';

    public ?Collection $products = null;
    public bool $showSearchbar = false;

    public function mount()
    {
        $this->search = request()->get('search');
    }

    public function getProductsFromSearch()
    {
        if ($this->search) {
            $this->products = Products::getBySearch(50, 'default', null, $this->search);
            $this->showSearchbar = true;
        } else {
            $this->products = null;
            $this->showSearchbar = false;
        }

        $this->dispatch('searchInitiated');
    }

    public function updatedSearch()
    {
        $this->getProductsFromSearch();
    }

    public function render()
    {
        return view(config('dashed-core.site_theme') . '.products.searchbar');
    }
}
