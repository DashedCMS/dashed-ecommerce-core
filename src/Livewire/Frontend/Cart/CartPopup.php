<?php

namespace Dashed\DashedEcommerceCore\Livewire\Frontend\Cart;

use Dashed\DashedEcommerceCore\Models\ShippingMethod;
use Livewire\Component;
use Illuminate\Support\Collection;
use Dashed\DashedTranslations\Models\Translation;
use Dashed\DashedEcommerceCore\Classes\ShoppingCart;
use Dashed\DashedEcommerceCore\Livewire\Concerns\CartActions;

class CartPopup extends Component
{
    use CartActions;

    public ?string $view = '';
    public bool $showCartPopup = false;
    public $cartSubtotal;
    public $cartTax;
    public $cartTotal;
    public $freeShippingThreshold;
    public int $freeShippingPercentage = 0;
    public string $cartType = 'default';

    //Needed for the CartActions
    public $shippingMethod;
    public $paymentMethod;

    protected $listeners = [
        'refreshCart' => 'updated',
    ];

    public function mount(?string $view = '')
    {
        $this->view = $view;

        self::updated();
    }

    public function updated()
    {
        $this->cartTotal = ShoppingCart::total();
        $this->cartSubtotal = ShoppingCart::subtotal();
        $this->cartTax = ShoppingCart::btw();
        $freeShippingMethod = ShippingMethod::where('sort', 'free_delivery')->first();
        $this->freeShippingThreshold = $freeShippingMethod ? $freeShippingMethod->minimum_order_value : Translation::get('free-shipping-treshold', 'cart-popup', 100, 'numeric');
        $isUnderThreshold = $this->cartTotal < $this->freeShippingThreshold;
        if ($isUnderThreshold) {
            $this->freeShippingPercentage = ($this->cartTotal / $this->freeShippingThreshold) * 100;
        } else {
            $this->freeShippingPercentage = 100;
        }
    }

    public function getCartItemsProperty(): Collection
    {
        return ShoppingCart::cartItems();
    }

    public function render()
    {
        return view(env('SITE_THEME', 'dashed') . '.cart.' . ($this->view ?: 'cart-popup'));
    }
}
