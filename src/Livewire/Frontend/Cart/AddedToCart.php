<?php

namespace Dashed\DashedEcommerceCore\Livewire\Frontend\Cart;

use Livewire\Component;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedTranslations\Models\Translation;
use Dashed\DashedEcommerceCore\Models\ShippingMethod;
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
        'productAddedToCart',
    ];

    public function mount(?string $view = '')
    {
        $this->view = $view;
    }

    public function productAddedToCart(Product $product)
    {
        cartHelper()->initialize();
        $this->showCartPopup = true;

        if ($this->product && $this->crossSellProducts && in_array($product->id, $this->crossSellProducts->pluck('id')->toArray())) {
            $this->crossSellProducts = $this->product->getCrossSellProducts(true, true);
        } else {
            $this->product = $product;
            $this->crossSellProducts = $product->getCrossSellProducts(true, true);
        }
        $this->cartTotal = cartHelper()->getTotal();
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
        return view(config('dashed-core.site_theme') . '.cart.' . ($this->view ?: 'added-to-cart-popup'));
    }
}
