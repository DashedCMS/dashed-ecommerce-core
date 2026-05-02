<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Livewire\Frontend\Cart;

use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Helpers\FreeShippingHelper;
use Dashed\DashedEcommerceCore\Services\CartSuggestions\CartProductSuggester;
use Illuminate\Support\Collection;
use Livewire\Component;

class CartSuggestions extends Component
{
    public string $view = 'cart';
    public ?int $limit = null;
    public ?int $boostSlots = null;

    public Collection $suggestions;
    public array $progress = ['gap' => 0.0, 'percentage' => 100, 'reached' => true];

    protected $listeners = ['refreshCart' => '$refresh'];

    public function mount(string $view = 'cart', ?int $limit = null, ?int $boostSlots = null): void
    {
        $this->view = in_array($view, ['cart', 'checkout', 'popup'], true) ? $view : 'cart';
        $this->limit = $limit;
        $this->boostSlots = $boostSlots;
        $this->suggestions = collect();
    }

    public function addToCart(int $productId): void
    {
        $product = \Dashed\DashedEcommerceCore\Models\Product::find($productId);

        if (! $product) {
            return;
        }

        $price = (float) $product->currentPrice;
        $discount = (float) ($product->discountPrice ?? $price);

        $attributes = [
            'discountPrice' => $discount,
            'originalPrice' => $price,
            'options' => [],
            'hiddenOptions' => [],
        ];

        cartHelper()->addToCart($productId, 1, $attributes);

        $this->dispatch('refreshCart');
        $this->dispatch('productAddedToCart', product: $product);
    }

    public function render()
    {
        $enabled = filter_var(Customsetting::get('cart_suggestions_enabled', null, '1'), FILTER_VALIDATE_BOOLEAN);

        if (! $enabled) {
            $this->suggestions = collect();

            return $this->resolveView();
        }

        cartHelper()->initialize();
        $cartItems = collect(cartHelper()->getCartItems());
        $cartProductIds = $cartItems->pluck('id')->filter()->unique()->values()->all();
        $cartTotal = (float) cartHelper()->getTotal();

        $limit = $this->limit ?? (int) Customsetting::get(
            'cart_suggestions_limit_'.$this->view,
            null,
            (string) $this->defaultLimit($this->view)
        );

        $boostSlots = $this->boostSlots ?? (int) Customsetting::get('cart_suggestions_boost_slots', null, '3');

        $this->suggestions = app(CartProductSuggester::class)->suggest(
            cartProductIds: $cartProductIds,
            cartTotal: $cartTotal,
            limit: $limit,
            boostSlots: $boostSlots,
            requireInStock: filter_var(Customsetting::get('cart_suggestions_require_in_stock', null, '1'), FILTER_VALIDATE_BOOLEAN),
            fallbackRandom: filter_var(Customsetting::get('cart_suggestions_fallback_random', null, '1'), FILTER_VALIDATE_BOOLEAN),
            gapMinFactor: (float) Customsetting::get('cart_suggestions_gap_min_factor', null, '0.8'),
            gapMaxFactor: (float) Customsetting::get('cart_suggestions_gap_max_factor', null, '1.5'),
        );

        $this->progress = app(FreeShippingHelper::class)->progress($cartTotal);

        return $this->resolveView();
    }

    private function resolveView()
    {
        $themeView = config('dashed-core.site_theme', 'dashed').'.cart.cart-suggestions-'.$this->view;
        $data = ['suggestions' => $this->suggestions, 'progress' => $this->progress];

        if (view()->exists($themeView)) {
            return view($themeView, $data);
        }

        $fallbackPath = __DIR__.'/../../../../resources/templates/cart/cart-suggestions-'.$this->view.'.blade.php';

        return view()->file($fallbackPath, $data);
    }

    private function defaultLimit(string $view): int
    {
        return match ($view) {
            'cart' => 6,
            'checkout' => 4,
            'popup' => 3,
            default => 6,
        };
    }
}
