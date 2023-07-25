<?php

namespace Qubiqx\QcommerceEcommerceCore\Livewire\Frontend\Products;

use Livewire\Component;
use Qubiqx\QcommerceEcommerceCore\Models\Product;

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
