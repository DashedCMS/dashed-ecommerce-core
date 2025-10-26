<?php

namespace Dashed\DashedEcommerceCore\Livewire\Frontend\Cart;

use Livewire\Component;
use Dashed\DashedEcommerceCore\Classes\ShoppingCart;
use Dashed\DashedEcommerceCore\Classes\TikTokHelper;
use Dashed\DashedEcommerceCore\Classes\CurrencyHelper;
use Dashed\DashedEcommerceCore\Livewire\Concerns\CartActions;

class Cart extends Component
{
    use CartActions;

    public ?string $discountCode = '';
    public $discount;
    public $subtotal;
    public $tax;
    public $total;
    //    public $paymentCosts;
    //    public $shippingCosts;
    //    public $depositAmount;
    //    public bool $postpayPaymentMethod = false;
    //    public array $paymentMethods = [];
    //    public array $depositPaymentMethods = [];
    public string $cartType = 'default';

    protected $listeners = [
        'refreshCart' => 'updated',
    ];

    public function mount(string $cartType = 'default')
    {
        cartHelper()->initialize();
        $this->discountCode = cartHelper()->getDiscountCodeString();
        //        $this->cartType = $cartType;
        //        ShoppingCart::setInstance($this->cartType);
        $this->checkCart();
        $this->fillPrices();

        $itemLoop = 0;
        $items = [];

        foreach ($this->cartItems as $cartItem) {
            $items[] = [
                'item_id' => $cartItem->model->id,
                'item_name' => $cartItem->model->name,
                'index' => $itemLoop,
                'discount' => $cartItem->model->discount_price > 0 ? number_format(($cartItem->model->discount_price - $cartItem->model->current_price), 2, '.', '') : 0,
                'item_category' => $cartItem->model->productCategories->first()?->name ?? null,
                'price' => number_format($cartItem->price, 2, '.', ''),
                'quantity' => $cartItem->qty,
            ];
            $itemLoop++;
        }

        $cartTotal = cartHelper()->getTotal();

        $this->dispatch('cartInitiated', [
            'cartTotal' => number_format($cartTotal, 2, '.', ''),
            'items' => $items,
            'tiktokItems' => TikTokHelper::getShoppingCartItems($cartTotal),
        ]);
    }

    public function fillPrices()
    {
        cartHelper()->initialize();
        $this->subtotal = CurrencyHelper::formatPrice(cartHelper()->getSubtotal());
        $discount = cartHelper()->getDiscount();
        $this->discount = $discount ? CurrencyHelper::formatPrice(cartHelper()->getDiscount()) : null;
        $this->tax = CurrencyHelper::formatPrice(cartHelper()->getTax());
        $this->total = CurrencyHelper::formatPrice(cartHelper()->getTotal());
        $this->getSuggestedProducts();
    }

    public function getCartItemsProperty()
    {
        return cartHelper()->getCartItems();
    }

    public function updated()
    {
        $this->fillPrices();
    }

    public function rules()
    {
        return [
//            'extras.*.value' => ['nullable'],
        ];
    }

    public function render()
    {
        return view(config('dashed-core.site_theme') . '.cart.' . ($this->cartType != 'default' ? $this->cartType . '-' : '') . 'cart');
    }
}
