<?php

namespace Dashed\DashedEcommerceCore\Livewire\Frontend\ProductFinder;

use Livewire\Component;
use Livewire\Attributes\Locked;
use Illuminate\Support\Facades\RateLimiter;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Models\ProductFinder;
use Dashed\DashedEcommerceCore\Services\ProductFinder\ProductFinderMatcher;

class ProductFinderQuiz extends Component
{
    /**
     * Elke afronding is een AI-aanroep op onze kosten; per IP per minuut.
     */
    public const FINISH_PER_MINUTE = 10;

    public const MAX_ANSWER_LENGTH = 255;

    // Alles hieronder wordt alleen server-side gezet. Locked, zodat een
    // bezoeker geen eigen antwoorden (prompt), resultaten (addAll) of
    // vinder kan inschieten.
    #[Locked]
    public ?int $finderId = null;

    #[Locked]
    public int $step = 0;

    /** @var array<string, string> */
    #[Locked]
    public array $answers = [];

    /** @var array<int, array<string, mixed>> */
    #[Locked]
    public array $results = [];

    #[Locked]
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
        if ($this->finished || ! $this->isKnownAnswer($questionLabel, $optionLabel)) {
            return;
        }

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

        $throttleKey = 'dashed-product-finder|'.(request()->ip() ?: 'unknown');

        if (RateLimiter::tooManyAttempts($throttleKey, self::FINISH_PER_MINUTE)) {
            $this->finished = true;
            $this->results = [];

            return;
        }

        RateLimiter::hit($throttleKey, 60);

        $answers = collect($this->answers)
            ->filter(fn ($option, $question) => is_string($question) && is_string($option) && $this->isKnownAnswer($question, $option))
            ->all();

        $matches = app(ProductFinderMatcher::class)->match($finder, $answers);

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
        $product = Product::find($productId);

        if (! $product) {
            return;
        }

        // Verplichte extra's kunnen alleen op de productpagina worden ingevuld.
        if ($product->hasRequiredExtras()) {
            $this->redirect($product->getUrl());

            return;
        }

        cartHelper()->addToCart($productId, 1, ['addedVia' => 'cross_sell']);
        $this->dispatch('refreshCart');
    }

    public function addAll(): void
    {
        foreach ($this->results as $result) {
            if (empty($result['id'])) {
                continue;
            }

            $product = Product::find((int) $result['id']);

            // Producten met verplichte extra's slaan we over; die kunnen
            // alleen via de productpagina in de winkelwagen.
            if (! $product || $product->hasRequiredExtras()) {
                continue;
            }

            cartHelper()->addToCart($product->id, 1, ['addedVia' => 'cross_sell']);
        }
        $this->dispatch('refreshCart');
    }

    /**
     * Alleen een vraag en antwoord die echt in de vinder staan, en nooit
     * langer dan MAX_ANSWER_LENGTH: de antwoorden gaan letterlijk de prompt in.
     */
    protected function isKnownAnswer(string $questionLabel, string $optionLabel): bool
    {
        if (mb_strlen($questionLabel) > self::MAX_ANSWER_LENGTH || mb_strlen($optionLabel) > self::MAX_ANSWER_LENGTH) {
            return false;
        }

        foreach ($this->questions as $question) {
            if ((string) ($question['label'] ?? '') !== $questionLabel) {
                continue;
            }

            foreach ((array) ($question['options'] ?? []) as $option) {
                if ((string) ($option['label'] ?? '') === $optionLabel) {
                    return true;
                }
            }
        }

        return false;
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
