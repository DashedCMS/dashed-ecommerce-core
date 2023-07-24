<?php

namespace Qubiqx\QcommerceEcommerceCore\Livewire\Frontend\Categories;

use Livewire\Component;
use Qubiqx\QcommerceEcommerceCore\Models\Product;
use Qubiqx\QcommerceTranslations\Models\Translation;
use Qubiqx\QcommerceEcommerceCore\Models\DiscountCode;
use Qubiqx\QcommerceEcommerceCore\Classes\ShoppingCart;
use Qubiqx\QcommerceEcommerceCore\Livewire\Concerns\CartActions;

class ShowCategories extends Component
{

    public function mount()
    {
    }

    public function render()
    {
        return view('qcommerce-ecommerce-core::frontend.categories.show-categories');
    }
}
