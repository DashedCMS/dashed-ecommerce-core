<?php

namespace Dashed\DashedEcommerceCore\Livewire\Frontend\Cart;

use Livewire\Component;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Livewire\Concerns\ProductCartActions;

class AddToCart extends Component
{
    use ProductCartActions;

    public function mount(Product $product)
    {
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
        return view('dashed-ecommerce-core::frontend.cart.add-to-cart');
    }
}
