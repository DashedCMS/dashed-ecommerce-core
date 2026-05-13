<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Livewire\Frontend\Cart;

use Livewire\Component;
use Illuminate\Support\Collection;
use Dashed\DashedEcommerceCore\Helpers\FreeShippingHelper;
use Dashed\DashedEcommerceCore\Services\Recommendations\Context\RecommendationContext;
use Dashed\DashedEcommerceCore\Services\Recommendations\RecommendationPlacement;
use Dashed\DashedEcommerceCore\Services\Recommendations\RecommendationService;

/**
 * Renders the recommendations strip below the cart (and optionally on the
 * checkout sidebar). Uses the shared `RecommendationService` rather than
 * calling `CartProductSuggester` directly — see T29 for the cart cutover
 * that swaps the legacy `CartSuggestions` callers onto this component.
 *
 * Defaults to the `cart` view; pass `view="checkout"` for the smaller
 * checkout-sidebar layout or `view="popup"` for popup-embedded use.
 */
class CartRecommendations extends Component
{
    public string $view = 'cart';
    public ?int $limit = null;
    public Collection $recommendations;
    public string $heading = '';
    public array $progress = ['gap' => 0.0, 'percentage' => 100, 'reached' => true];

    protected $listeners = [
        'refreshCart' => '$refresh',
        'productAddedToCart' => '$refresh',
    ];

    public function mount(string $view = 'cart', ?int $limit = null): void
    {
        $this->view = in_array($view, ['cart', 'checkout', 'popup'], true) ? $view : 'cart';
        $this->limit = $limit;
        $this->recommendations = collect();
    }

    public function render()
    {
        cartHelper()->initialize();
        $cartItems = collect(cartHelper()->getCartItems());
        $cartProducts = $cartItems->map(fn ($i) => $i->model ?? null)->filter()->values();
        $cartTotal = (float) cartHelper()->getTotal();

        try {
            $this->progress = app(FreeShippingHelper::class)->progress($cartTotal);
        } catch (\Throwable) {
            $this->progress = ['gap' => 0.0, 'percentage' => 100, 'reached' => true];
        }

        $placement = match ($this->view) {
            'checkout' => RecommendationPlacement::Checkout,
            'popup' => RecommendationPlacement::Popup,
            default => RecommendationPlacement::Cart,
        };

        $limit = $this->limit ?? ($this->view === 'checkout' ? 2 : 4);

        $context = RecommendationContext::for($placement)
            ->withCurrentProducts($cartProducts->all())
            ->withLimit($limit)
            ->withExtra('cart_total', $cartTotal)
            ->withExtra('shipping_gap', $this->progress['gap'] ?? 0.0)
            ->build();

        $result = app(RecommendationService::class)->for($context);
        $this->recommendations = $result->products;
        $this->heading = $result->heading ?? $placement->heading();

        return view('dashed-ecommerce-core::livewire.frontend.cart.cart-recommendations', [
            'recommendations' => $this->recommendations,
            'progress' => $this->progress,
            'view' => $this->view,
            'heading' => $this->heading,
        ]);
    }
}
