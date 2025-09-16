<?php

namespace Dashed\DashedEcommerceCore\Livewire\Frontend\Categories;

use Livewire\Component;
use Illuminate\Database\Eloquent\Collection;
use Dashed\DashedEcommerceCore\Models\ProductCategory;
use Dashed\DashedEcommerceCore\Classes\ProductCategories;

class ShowCategories extends Component
{
    public ?Collection $productCategories = null;
    public ?ProductCategory $singleProductCategory = null;

    public function mount(?Collection $productCategories = null, ?ProductCategory $productCategory = null)
    {
        $this->productCategories = $productCategories ?: ProductCategories::getTopLevel(100);
        $this->singleProductCategory = $productCategory;
    }

    public function render()
    {
        return view(config('dashed-core.site_theme') . '.categories.show-categories');
    }
}
