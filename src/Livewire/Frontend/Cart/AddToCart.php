<?php

namespace Dashed\DashedEcommerceCore\Livewire\Frontend\Cart;

use Livewire\Component;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Livewire\Concerns\ProductCartActions;

class AddToCart extends Component
{
    use ProductCartActions;

    public ?string $view = '';

    public function mount(Product $product, ?string $view = '')
    {
        $this->productGroup = $product->productGroup;
        $this->originalProduct = $product ?? null;
        $this->product = $product ?? null;
        $this->view = $view;

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
        return view(config('dashed-core.site_theme') . '.cart.' . ($this->view ?: 'add-to-cart'));
    }
}
