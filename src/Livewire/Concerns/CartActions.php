<?php

namespace Qubiqx\QcommerceEcommerceCore\Livewire\Concerns;

use Qubiqx\QcommerceEcommerceCore\Classes\ShoppingCart;

trait CartActions
{
    public function checkCart(?string $status = null, ?string $message = null)
    {
        if ($status) {
            $this->emit('showAlert', $status, $message);
        }

        ShoppingCart::removeInvalidItems();

        $this->emit('refreshCart');
    }
}
