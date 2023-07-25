<?php

namespace Qubiqx\QcommerceEcommerceCore\Livewire\Frontend\Products;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Livewire\Component;
use Livewire\WithPagination;
use Qubiqx\QcommerceCore\Models\Customsetting;
use Qubiqx\QcommerceEcommerceCore\Classes\ProductCategories;
use Qubiqx\QcommerceEcommerceCore\Classes\Products;
use Qubiqx\QcommerceEcommerceCore\Models\Product;
use Qubiqx\QcommerceEcommerceCore\Models\ProductCategory;

class ShowProduct extends Component
{
    public Product $product;
    public $characteristics;
    public $suggestedProducts;

    public function mount(Product $product)
    {
        $this->product = $product;
        $this->characteristics = $product->showableCharacteristics();
        $this->suggestedProducts = $product->getSuggestedProducts();
    }
    public function render()
    {
        return view('qcommerce-ecommerce-core::frontend.products.show-product');
    }
}
