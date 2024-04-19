<?php

namespace Dashed\DashedEcommerceCore\Livewire\Frontend\Products;

use Livewire\Component;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Livewire\Concerns\ProductCartActions;

class ShowProduct extends Component
{
    use ProductCartActions;

    protected $listeners = [
        'setProductExtraCustomValue',
    ];

    public function mount(Product $product)
    {
        $this->parentProduct = $product->parent ? $product->parent : $product;
        $this->originalProduct = $product;

        $this->fillInformation(true);
    }

    public function updated()
    {
        $this->fillInformation();
    }

    public function rules()
    {
        return [
            'extras.*.value' => ['nullable'],
            'files.*' => ['nullable', 'file'],
        ];
    }

    public function render()
    {
        return view('dashed-ecommerce-core::frontend.products.show-product');
    }
}
