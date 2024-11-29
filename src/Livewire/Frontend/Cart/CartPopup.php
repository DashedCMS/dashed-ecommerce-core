<?php

namespace Dashed\DashedEcommerceCore\Livewire\Frontend\Cart;

use Livewire\Component;
use Dashed\DashedEcommerceCore\Livewire\Concerns\ProductCartActions;

class CartPopup extends Component
{
    use ProductCartActions;

    public ?string $view = '';

    public function mount(?string $view = '')
    {
        //        $this->parentProduct = $product->parent ? $product->parent : $product;
        //        $this->originalProduct = $product;
        $this->view = $view;

        //        $this->fillInformation(true);
    }

    public function updated()
    {
        //        $this->fillInformation();
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
        return view(env('SITE_THEME', 'dashed') . '.cart.' . ($this->view ?: 'cart-popup'));
    }
}
