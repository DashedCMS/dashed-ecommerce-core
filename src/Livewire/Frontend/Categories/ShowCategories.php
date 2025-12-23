<?php

namespace Dashed\DashedEcommerceCore\Livewire\Frontend\Categories;

use Dashed\DashedCore\Models\Customsetting;
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
        $this->productCategories = $productCategories ?: ProductCategories::getTopLevel(100, orderBy: Customsetting::get('product_categories_default_order_by', 'created_at'), order: Customsetting::get('product_categories_default_order', 'DESC'));
        $this->singleProductCategory = $productCategory;
    }

    public function render()
    {
        return view(config('dashed-core.site_theme', 'dashed') . '.categories.show-categories');
    }
}
