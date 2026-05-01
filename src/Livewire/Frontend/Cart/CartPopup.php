<?php

namespace Dashed\DashedEcommerceCore\Livewire\Frontend\Cart;

use Livewire\Component;
use Illuminate\Support\Collection;
use Dashed\DashedEcommerceCore\Helpers\FreeShippingHelper;
use Dashed\DashedEcommerceCore\Livewire\Concerns\CartActions;

class CartPopup extends Component
{
    use CartActions;

    public bool $initialized = false;
    public ?string $view = '';
    public bool $showCartPopup = false;
    public $cartSubtotal;
    public $cartTax;
    public $cartTotal;
    public $freeShippingThreshold;
    public int $freeShippingPercentage = 0;
    public $cartType = 'default';
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
        if ($this->showCartPopup || ! $this->initialized) {
            cartHelper()->initialize();
            $this->cartItems = cartHelper()->getCartItems();
            $this->cartTotal = cartHelper()->getTotal();
            $this->cartSubtotal = cartHelper()->getSubtotal();
            $this->cartTax = cartHelper()->getTax();
            $helper = app(FreeShippingHelper::class);
            $progress = $helper->progress((float) $this->cartTotal);
            $this->freeShippingThreshold = $helper->threshold();
            $this->freeShippingPercentage = $progress['percentage'];
            $this->initialized = true;
        }
    }

    public function getCartItemsProperty(): array|Collection
    {
        return $this->cartItems;
    }

    public function render()
    {
        return view(config('dashed-core.site_theme', 'dashed') . '.cart.' . ($this->view ?: 'cart-popup'));
    }
}
