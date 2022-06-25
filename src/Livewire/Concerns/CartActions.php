<?php

namespace Qubiqx\QcommerceEcommerceCore\Livewire\Concerns;

use Gloudemans\Shoppingcart\Facades\Cart;
use Qubiqx\QcommerceCore\Classes\Sites;
use Qubiqx\QcommerceCore\Models\Customsetting;
use Qubiqx\QcommerceEcommerceCore\Classes\ShoppingCart;
use Qubiqx\QcommerceEcommerceCore\Models\Product;
use Qubiqx\QcommerceEcommerceCore\Models\ProductExtraOption;
use Qubiqx\QcommerceTranslations\Models\Translation;

trait CartActions
{


    public function checkCart(string $status, string $message)
    {
        $this->emit('showAlert', $status, $message);

        ShoppingCart::removeInvalidItems();

        $this->emit('refreshCart');
    }
}
