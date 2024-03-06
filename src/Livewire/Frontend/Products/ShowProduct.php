<?php

namespace Dashed\DashedEcommerceCore\Livewire\Frontend\Products;

use Livewire\Component;
use Illuminate\Database\Eloquent\Collection;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Livewire\Concerns\CartActions;

class ShowProduct extends Component
{
    use CartActions;

    public Product $product;
    public $characteristics;
    public $suggestedProducts;
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
        $this->characteristics = $product->showableCharacteristics();
        $this->suggestedProducts = $product->getSuggestedProducts();
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
        return view('dashed-ecommerce-core::frontend.products.show-product');
    }
}
