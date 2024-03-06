<?php

namespace Dashed\DashedEcommerceCore\Livewire\Frontend\Cart;

use Livewire\Component;
use Livewire\WithFileUploads;
use Illuminate\Database\Eloquent\Collection;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Livewire\Concerns\CartActions;

class AddToCart extends Component
{
    use CartActions;
    use WithFileUploads;

    public Product $product;
    public array $filters = [];
    public ?Collection $productExtras = null;
    public ?array $extras = [];
    public string|int $quantity = 1;
    public array $files = [];
    public string $cartType = 'default';
    public $price = 0;
    public $discountPrice = 0;

    public function mount(Product $product)
    {
        $this->product = $product;
        $this->filters = $this->product->filters();
        $this->productExtras = $this->product->allProductExtras();
        $this->extras = $this->product->allProductExtras()->toArray();
        $this->price = $this->product->currentPrice;
        $this->discountPrice = $this->product->discountPrice;
    }

    public function rules()
    {
        return [
            'extras.*.value' => ['nullable'],
            'files.*' => ['nullable', 'file'],
        ];
    }

    public function render()
    {
        return view('dashed-ecommerce-core::frontend.cart.add-to-cart');
    }
}
