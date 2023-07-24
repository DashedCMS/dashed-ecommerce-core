<?php

namespace Qubiqx\QcommerceEcommerceCore\Livewire\Frontend\Categories;

use Illuminate\Database\Eloquent\Collection;
use Livewire\Component;
use Qubiqx\QcommerceEcommerceCore\Classes\ProductCategories;
use Qubiqx\QcommerceEcommerceCore\Models\ProductCategory;

class ShowCategories extends Component
{
    public ?Collection $productCategories = null;
    public ?ProductCategory $productCategory = null;

    public function mount(?Collection $productCategories = null, ?ProductCategory $productCategory = null)
    {
        $this->productCategories = $productCategories ?: ProductCategories::getTopLevel(100);
        $this->productCategory = $productCategory;
    }

    public function render()
    {
        return view('qcommerce-ecommerce-core::frontend.categories.show-categories');
    }
}
