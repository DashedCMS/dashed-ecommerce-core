<?php

namespace Dashed\DashedEcommerceCore\Livewire\Frontend\Cart;

use Livewire\Component;
use Illuminate\Support\Collection;
use Dashed\DashedCore\Models\Customsetting;
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

            // De popup toont alleen de producten. Op de kassa staat de verzend- en
            // betaalmethode al in de wagen; die kosten (en hun btw) zitten dan in
            // het totaal en, bij prijzen incl. btw, ook in het subtotaal, terwijl
            // de popup er geen regel voor heeft.
            $extraCosts = cartHelper()->getShippingCosts() + cartHelper()->getPaymentCosts();
            $extraVat = cartHelper()->getVatForShippingMethod() + cartHelper()->getVatForPaymentMethod();
            $subtotal = cartHelper()->getSubtotal();
            $total = cartHelper()->getTotal() - $extraCosts;
            if (Customsetting::get('taxes_prices_include_taxes')) {
                $subtotal -= $extraCosts;
            } else {
                $total -= $extraVat;
            }

            $this->cartTotal = round(max(0, $total), 2);
            $this->cartSubtotal = round(max(0, $subtotal), 2);
            $this->cartTax = round(max(0, cartHelper()->getTax() - $extraVat), 2);
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

    public function placeholder()
    {
        // Empty overlay div - no visible content, no layout shift while lazy-loading.
        return '<div aria-hidden="true"></div>';
    }

    public function render()
    {
        return view(config('dashed-core.site_theme', 'dashed') . '.cart.' . ($this->view ?: 'cart-popup'));
    }
}
