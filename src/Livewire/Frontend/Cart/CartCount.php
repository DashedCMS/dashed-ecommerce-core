<?php

namespace Dashed\DashedEcommerceCore\Livewire\Frontend\Cart;

use Livewire\Component;

class CartCount extends Component
{
    // Bewust geen publieke `$cartCount`: Livewire laat een browser elke publieke
    // eigenschap zetten via de `updates` in het verzoek, en een array daarin werd
    // een TypeError in htmlspecialchars() zodra de view hem echode. Het aantal is
    // afgeleide staat en wordt per render uit de winkelwagen zelf gelezen.
    public $cartType = 'default';

    protected $listeners = [
        'refreshCart',
    ];

    public function mount($cartType = 'default'): void
    {
        if (is_array($cartType)) {
            $cartType = $cartType[0] ?? 'default';
        }

        $this->cartType = (string) $cartType;
    }

    public function refreshCart(): void
    {
        // Elke afgehandelde gebeurtenis rendert het component opnieuw, en render()
        // leest het aantal vers uit de winkelwagen. Hier hoeft niets te gebeuren.
    }

    public function placeholder()
    {
        // Same-size invisible badge so there is no layout shift while lazy-loading.
        return '<span class="cart-count" aria-hidden="true"></span>';
    }

    public function render()
    {
        return view(config('dashed-core.site_theme', 'dashed') . '.cart.cart-count', [
            'cartCount' => $this->cartCount(),
        ]);
    }

    protected function cartCount(): int
    {
        // setCartType() dwingt zelf een bruikbare string of null af; $cartType is
        // publiek en dus ook door de client te zetten.
        cartHelper()->setCartType($this->cartType);

        return cartHelper()->getCartItems()->count();
    }
}
