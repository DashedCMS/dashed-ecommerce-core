<?php

namespace Dashed\DashedEcommerceCore\Livewire\Frontend\Products;

use Dashed\DashedEcommerceCore\Classes\Products;
use Livewire\Component;
use Illuminate\Database\Eloquent\Collection;

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
            $this->products = Products::getProductsWithSearch($this->search);
            $this->showSearchbar = true;
        } else {
            $this->products = null;
            $this->showSearchbar = false;
        }
    }

    public function updatedSearch()
    {
        $this->getProductsFromSearch();
    }

    public function render()
    {
        return view('dashed-ecommerce-core::frontend.products.searchbar');
    }
}
