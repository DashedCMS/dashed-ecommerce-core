<?php

namespace Dashed\DashedEcommerceCore\Livewire\Frontend\OrderAddOns;

use Livewire\Component;
use Livewire\Attributes\Locked;
use Illuminate\Support\Collection;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Classes\ShoppingCart;
use Dashed\DashedEcommerceCore\Models\EcommerceActionLog;
use Dashed\DashedEcommerceCore\Services\Meta\AddToCartPayload;
use Dashed\DashedEcommerceCore\Classes\OrderAddOns\CheckoutMode;
use Dashed\DashedEcommerceCore\Classes\OrderAddOns\Suggestions as OrderAddOnSuggestions;

/**
 * De voorstellen op /bijbestellen/{order}: toevoegen legt het product in de gewone wagen (zoals
 * CartSuggestions, met addedVia `bijbestellen`); een product met een verplichte extra opent de
 * quick-add-modal. Een thema overschrijft de weergave met <site_theme>/order-add-ons/suggestions.
 */
class Suggestions extends Component
{
    public const ADDED_VIA = 'bijbestellen';

    #[Locked]
    public int $orderId;

    public ?int $quickAddProductId = null;

    protected $listeners = [
        'refreshCart' => '$refresh',
        'productAddedToCart' => 'closeQuickAdd',
    ];

    public function mount(int $orderId): void
    {
        $this->orderId = $orderId;
    }

    public function addToCart(int $productId): void
    {
        // Alleen wat we hier zelf voorstellen, en alleen voor de order uit de ondertekende link.
        if ((int) CheckoutMode::parent()?->id !== $this->orderId || ! $this->suggestions()->contains('id', $productId)) {
            return;
        }

        $product = Product::find($productId);

        if (! $product) {
            return;
        }

        if ($product->hasRequiredExtras()) {
            $this->quickAddProductId = $product->id;

            return;
        }

        // Cart-math gebruikt altijd incl-prijzen (zie CartSuggestions).
        $price = (float) $product->priceForUser();
        $rawDiscount = $product->getRawOriginal('discount_price');
        $discount = (float) ($rawDiscount > 0 ? $rawDiscount : $price);

        EcommerceActionLog::createLog('add_to_cart', 1, productId: $product->id);

        cartHelper()->addToCart($productId, 1, [
            'discountPrice' => $discount,
            'originalPrice' => $price,
            'options' => [],
            'hiddenOptions' => [],
            'addedVia' => self::ADDED_VIA,
        ]);

        $this->dispatch('refreshCart');
        $this->dispatch('productAddedToCart', AddToCartPayload::for($product, 1, $price));
    }

    public function closeQuickAdd(): void
    {
        $this->quickAddProductId = null;
    }

    public function render()
    {
        cartHelper()->initialize();

        $inCart = collect(cartHelper()->getCartItems())->pluck('id')->filter()->map(fn ($id) => (int) $id)->all();
        $theme = config('dashed-core.site_theme', 'dashed').'.order-add-ons.suggestions';

        return view(view()->exists($theme) ? $theme : 'dashed-ecommerce-core::order-add-ons.suggestions', [
            'suggestions' => $this->suggestions(),
            'inCart' => $inCart,
            'cartUrl' => ShoppingCart::getCartUrl(),
            'checkoutUrl' => ShoppingCart::getCheckoutUrl(),
        ]);
    }

    /** @return Collection<int, Product> */
    private function suggestions(): Collection
    {
        $order = Order::find($this->orderId);

        return $order ? OrderAddOnSuggestions::for($order) : collect();
    }
}
