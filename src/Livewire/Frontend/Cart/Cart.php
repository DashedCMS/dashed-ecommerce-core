<?php

namespace Dashed\DashedEcommerceCore\Livewire\Frontend\Cart;

use Livewire\Component;
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

    public string $cartType = 'default';

    protected $listeners = [
        'refreshCart' => 'updated',
    ];

    public function mount(string $cartType = 'default')
    {
        $this->cartType = $cartType ?: 'default';

        // Zorg dat cartHelper in de juiste instance zit
        cartHelper()->initialize($this->cartType);
        cartHelper()->setCartType($this->cartType);

        $this->discountCode = cartHelper()->getDiscountCodeString();

        $this->checkCart();
        $this->fillPrices();

        $itemLoop = 0;
        $items = [];

        foreach ($this->cartItems as $cartItem) {
            $model = $cartItem->model ?? null;

            if (! $model) {
                continue;
            }

            $items[] = [
                'item_id' => $model->id,
                'item_name' => $model->name,
                'index' => $itemLoop,
                'discount' => ($model->discount_price ?? 0) > 0
                    ? number_format((($model->discount_price ?? 0) - ($model->current_price ?? 0)), 2, '.', '')
                    : 0,
                'item_category' => $model->productCategories->first()?->name ?? null,
                'price' => number_format((float) ($cartItem->price ?? 0), 2, '.', ''),
                'quantity' => (int) ($cartItem->qty ?? 0),
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

    public function fillPrices(): void
    {
        cartHelper()->initialize($this->cartType);
        cartHelper()->setCartType($this->cartType);

        $this->subtotal = CurrencyHelper::formatPrice(cartHelper()->getSubtotal());

        $discount = cartHelper()->getDiscount();
        $this->discount = $discount ? CurrencyHelper::formatPrice($discount) : null;

        $this->tax = CurrencyHelper::formatPrice(cartHelper()->getTax());
        $this->total = CurrencyHelper::formatPrice(cartHelper()->getTotal());

        $this->getSuggestedProducts();
    }

    public function getCartItemsProperty()
    {
        cartHelper()->initialize($this->cartType);
        cartHelper()->setCartType($this->cartType);

        return cartHelper()->getCartItems();
    }

    public function updated(): void
    {
        $this->fillPrices();
    }

    public function rules(): array
    {
        return [
            // 'extras.*.value' => ['nullable'],
        ];
    }

    public function render()
    {
        return view(
            config('dashed-core.site_theme', 'dashed')
            . '.cart.'
            . ($this->cartType != 'default' ? $this->cartType . '-' : '')
            . 'cart'
        );
    }
}
