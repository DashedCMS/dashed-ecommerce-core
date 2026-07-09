<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Livewire\Frontend\Products;

use Livewire\Component;
use Dashed\DashedCore\Classes\Sites;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Services\BackInStockService;

class StockNotification extends Component
{
    public Product $product;

    public string $email = '';

    public bool $submitted = false;

    public bool $alreadySubscribed = false;

    public function mount(Product $product): void
    {
        $this->product = $product;
    }

    public function getShouldShowProperty(): bool
    {
        return ! $this->product->hasDirectSellableStock()
            && ! $this->product->outOfStockSellable();
    }

    protected function rules(): array
    {
        return [
            'email' => ['required', 'email'],
        ];
    }

    public function submit(BackInStockService $service): void
    {
        $this->validate();

        $notification = $service->subscribe(
            Sites::getActive() ?? 'default',
            $this->product->id,
            $this->email,
        );

        $this->alreadySubscribed = ! $notification->wasRecentlyCreated;
        $this->submitted = true;
    }

    public function render()
    {
        return view('dashed-ecommerce-core::livewire.frontend.stock-notification');
    }
}
