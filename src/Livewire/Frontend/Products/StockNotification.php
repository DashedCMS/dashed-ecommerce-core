<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Livewire\Frontend\Products;

use Livewire\Component;
use Livewire\Attributes\On;
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

    /**
     * De productpagina wisselt van variant zonder herladen. Zonder dit
     * meldde de klant zich aan voor de variant waarmee de pagina opende,
     * niet voor de maat of kleur die hij net koos.
     */
    #[On('productUpdated')]
    public function variantChanged(array $data = []): void
    {
        $productId = (int) ($data['productId'] ?? 0);

        if (! $productId || $productId === (int) $this->product->id) {
            return;
        }

        // Zelfde productgroep is de grens; site en zichtbaarheid volgen daaruit.
        $variant = Product::query()->where('public', true)->find($productId);

        if (! $variant || ! $this->product->product_group_id || (int) $variant->product_group_id !== (int) $this->product->product_group_id) {
            return;
        }

        $this->product = $variant;
        $this->submitted = false;
        $this->alreadySubscribed = false;
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
            Sites::getActive(),
            $this->product->id,
            $this->email,
            app()->getLocale(),
        );

        $this->alreadySubscribed = ! $notification->wasRecentlyCreated;
        $this->submitted = true;
    }

    public function render()
    {
        return view('dashed-ecommerce-core::livewire.frontend.stock-notification');
    }
}
