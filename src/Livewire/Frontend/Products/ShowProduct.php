<?php

namespace Dashed\DashedEcommerceCore\Livewire\Frontend\Products;

use Livewire\Component;
use Dashed\DashedEcommerceCore\Livewire\Concerns\ProductCartActions;

class ShowProduct extends Component
{
    use ProductCartActions;

    protected $listeners = [
        'setProductExtraCustomValue',
        'addToCart'
    ];

    public function mount($product = null, $productGroup = null)
    {
        $this->productGroup = $productGroup ?: $product->productGroup;
        $this->originalProduct = $product ?? null;
        $this->product = $product ?? null;

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
        return view(env('SITE_THEME', 'dashed') . '.products.show-product');
    }
}
