<?php

namespace Dashed\DashedEcommerceCore\Livewire\Frontend\Cart;

use Livewire\Component;
use Illuminate\Support\Collection;
use Dashed\DashedTranslations\Models\Translation;
use Dashed\DashedEcommerceCore\Models\ShippingMethod;
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
    private $cartItems = [];

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

    public function fillPrices(): void
    {
        $this->updated();
    }

    public function updated()
    {
        cartHelper()->initialize();
        $this->cartItems = cartHelper()->getCartItems();
        $this->cartTotal = cartHelper()->getTotal();
        $this->cartSubtotal = cartHelper()->getSubtotal();
        $this->cartTax = cartHelper()->getTax();
        $freeShippingMethod = ShippingMethod::where('sort', 'free_delivery')->first();
        $this->freeShippingThreshold = $freeShippingMethod ? $freeShippingMethod->minimum_order_value : Translation::get('free-shipping-treshold', 'cart-popup', 100, 'numeric');
        $isUnderThreshold = $this->cartTotal < $this->freeShippingThreshold;
        if ($isUnderThreshold) {
            $this->freeShippingPercentage = number_format(($this->cartTotal / $this->freeShippingThreshold) * 100, 0);
        } else {
            $this->freeShippingPercentage = 100;
        }
    }

    public function getCartItemsProperty(): array|Collection
    {
        return $this->cartItems;
    }

    public function render()
    {
        return view(config('dashed-core.site_theme') . '.cart.' . ($this->view ?: 'cart-popup'));
    }
}
