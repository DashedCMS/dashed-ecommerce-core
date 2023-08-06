<?php

namespace Dashed\DashedEcommerceCore\Livewire\Frontend\Products;

use Livewire\Component;
use Dashed\DashedEcommerceCore\Models\Product;

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
        return view('dashed-ecommerce-core::frontend.products.show-product');
    }
}
