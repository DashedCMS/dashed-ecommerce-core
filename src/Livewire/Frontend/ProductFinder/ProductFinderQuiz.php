<?php

namespace Dashed\DashedEcommerceCore\Livewire\Frontend\ProductFinder;

use Livewire\Component;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Models\ProductFinder;
use Dashed\DashedEcommerceCore\Services\ProductFinder\ProductFinderMatcher;

class ProductFinderQuiz extends Component
{
    public ?int $finderId = null;

    public int $step = 0;

    /** @var array<string, string> */
    public array $answers = [];

    /** @var array<int, array<string, mixed>> */
    public array $results = [];

    public bool $finished = false;

    public function mount(array $blockData = []): void
    {
        $this->finderId = isset($blockData['finder_id']) ? (int) $blockData['finder_id'] : null;
    }

    public function getFinderProperty(): ?ProductFinder
    {
        if (! $this->finderId) {
            return null;
        }

        return ProductFinder::query()->where('is_active', true)->find($this->finderId);
    }

    /** @return array<int, array<string, mixed>> */
    public function getQuestionsProperty(): array
    {
        return (array) ($this->finder?->questions ?? []);
    }

    public function selectAnswer(string $questionLabel, string $optionLabel): void
    {
        $this->answers[$questionLabel] = $optionLabel;
        $this->step++;

        if ($this->step >= count($this->questions)) {
            $this->finish();
        }
    }

    public function finish(): void
    {
        $finder = $this->finder;
        if (! $finder) {
            $this->finished = true;
            $this->results = [];

            return;
        }

        $matches = app(ProductFinderMatcher::class)->match($finder, $this->answers);

        $this->results = array_map(function (array $row) {
            /** @var Product $product */
            $product = $row['product'];

            return [
                'id' => (int) $product->id,
                'name' => (string) $product->name,
                'price' => (float) ($product->current_price ?? $product->price ?? 0),
                'reason' => (string) ($row['reason'] ?? ''),
                'url' => $product->getUrl(),
            ];
        }, $matches);

        $this->finished = true;
    }

    public function addToCart(int $productId): void
    {
        cartHelper()->addToCart($productId, 1, ['addedVia' => 'cross_sell']);
        $this->dispatch('refreshCart');
    }

    public function addAll(): void
    {
        foreach ($this->results as $result) {
            if (! empty($result['id'])) {
                cartHelper()->addToCart((int) $result['id'], 1, ['addedVia' => 'cross_sell']);
            }
        }
        $this->dispatch('refreshCart');
    }

    public function restart(): void
    {
        $this->step = 0;
        $this->answers = [];
        $this->results = [];
        $this->finished = false;
    }

    public function render()
    {
        return view('dashed-ecommerce-core::livewire.frontend.product-finder-quiz');
    }
}
