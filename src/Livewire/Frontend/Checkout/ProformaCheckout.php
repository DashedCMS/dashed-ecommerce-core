<?php

namespace Dashed\DashedEcommerceCore\Livewire\Frontend\Checkout;

use Livewire\Component;

class ProformaCheckout extends Component
{
    public string $orderHash = '';

    public function mount(string $orderHash): void
    {
        $this->orderHash = $orderHash;
    }

    public function render()
    {
        return view('dashed-ecommerce-core::livewire.frontend.checkout.proforma-checkout');
    }
}
