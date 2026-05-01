<?php

namespace Dashed\DashedEcommerceCore\Livewire\Frontend\Cart;

use Livewire\Component;
use Dashed\DashedEcommerceCore\Helpers\FreeShippingHelper;
use Dashed\DashedEcommerceCore\Livewire\Concerns\ProductCartActions;
use Dashed\DashedEcommerceCore\Models\Product;

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
        $helper = app(FreeShippingHelper::class);
        $progress = $helper->progress((float) $this->cartTotal);
        $this->freeShippingThreshold = $helper->threshold();
        $this->freeShippingPercentage = $progress['percentage'];
    }

    public function render()
    {
        return view(config('dashed-core.site_theme', 'dashed') . '.cart.' . ($this->view ?: 'added-to-cart-popup'));
    }
}
