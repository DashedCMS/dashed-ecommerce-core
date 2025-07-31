<?php

namespace Dashed\DashedEcommerceCore\Livewire\Frontend\Cart;

use Dashed\DashedEcommerceCore\Classes\ShoppingCart;
use Dashed\DashedEcommerceCore\Livewire\Concerns\CartActions;
use Dashed\DashedEcommerceCore\Models\ShippingMethod;
use Dashed\DashedTranslations\Models\Translation;
use Illuminate\Support\Collection;
use Livewire\Component;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Livewire\Concerns\ProductCartActions;

class AddedToCart extends Component
{
    use ProductCartActions;

    public ?string $view = '';
    public bool $showCartPopup = false;
    public ?Product $product = null;
    public $cartTotal;
    public $freeShippingThreshold;
    public int $freeShippingPercentage = 0;

    protected $listeners = [
        'productAddedToCart'
    ];

    public function mount(?string $view = '')
    {
        $this->view = $view;
    }

    public function productAddedToCart(Product $product)
    {
        $this->showCartPopup = true;
        $this->product = $product;
        $this->crossSellProducts = $product->getCrossSellProducts(true, true);
        $this->cartTotal = ShoppingCart::total();
        $freeShippingMethod = ShippingMethod::where('sort', 'free_delivery')->first();
        $this->freeShippingThreshold = $freeShippingMethod ? $freeShippingMethod->minimum_order_value : Translation::get('free-shipping-treshold', 'cart-popup', 100, 'numeric');
        $isUnderThreshold = $this->cartTotal < $this->freeShippingThreshold;
        if ($isUnderThreshold) {
            $this->freeShippingPercentage = ($this->cartTotal / $this->freeShippingThreshold) * 100;
        } else {
            $this->freeShippingPercentage = 100;
        }
    }

    public function render()
    {
        return view(env('SITE_THEME', 'dashed') . '.cart.' . ($this->view ?: 'added-to-cart-popup'));
    }
}
