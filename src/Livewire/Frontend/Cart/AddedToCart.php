<?php

namespace Dashed\DashedEcommerceCore\Livewire\Frontend\Cart;

use Livewire\Component;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Livewire\Concerns\ProductCartActions;

class AddedToCart extends Component
{
    public ?string $view = '';

    public function mount(?string $view = '')
    {
        $this->view = $view;
    }

    public function render()
    {
        return view(env('SITE_THEME', 'dashed') . '.cart.' . ($this->view ?: 'added-to-cart'));
    }
}
