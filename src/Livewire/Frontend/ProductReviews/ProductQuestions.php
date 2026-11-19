<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Livewire\Frontend\ProductReviews;

use Livewire\Component;
use Livewire\Attributes\Locked;
use Dashed\DashedCore\Classes\Sites;
use Dashed\DashedCore\Classes\RateLimits;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Models\ProductQuestion;
use Dashed\DashedEcommerceCore\Services\ProductReviews\ProductQuestionService;
use Dashed\DashedEcommerceCore\Services\ProductReviews\ProductReviewSettings;

/**
 * Vragen en antwoorden op de productpagina, met een formulier om een vraag
 * te stellen. Thema: <livewire:product-reviews.product-questions :product="$product" />.
 * Tegen spam: een honeypotveld (`website`, onzichtbaar voor mensen; ingevuld
 * doet alsof het gelukt is en slaat niets op) en de verzoeklimiet
 * `dashed-product-questions` per IP en per e-mailadres.
 */
class ProductQuestions extends Component
{
    public const PER_PAGE = 5;

    #[Locked]
    public int $productId;

    public int $limit = self::PER_PAGE;

    public string $name = '';

    public string $email = '';

    public string $question = '';

    public string $website = '';

    public bool $submitted = false;

    public ?int $retryAfter = null;

    public function mount(Product|int $product): void
    {
        $this->productId = $product instanceof Product ? (int) $product->getKey() : $product;
    }

    public function loadMore(): void
    {
        $this->limit = min($this->limit + self::PER_PAGE, 200);
    }

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:80'],
            'email' => ['required', 'email', 'max:191'],
            'question' => ['required', 'string', 'min:5', 'max:2000'],
        ];
    }

    public function submit(): void
    {
        if (! ProductReviewSettings::questionsEnabled()) {
            return;
        }

        $this->validate();

        if ($this->website !== '') {
            $this->reset(['question']);
            $this->submitted = true;

            return;
        }

        $this->retryAfter = RateLimits::hit('dashed-product-questions', 'ip:' . (request()->ip() ?: 'unknown'), 3)
            ?? RateLimits::hit('dashed-product-questions', 'email:' . mb_strtolower(trim($this->email)));

        if ($this->retryAfter !== null) {
            return;
        }

        $product = Product::find($this->productId);
        if (! $product) {
            return;
        }

        ProductQuestionService::ask($product, $this->name, $this->email, $this->question, (string) Sites::getActive());

        $this->reset(['question']);
        $this->submitted = true;
    }

    public function render()
    {
        $product = ProductReviewSettings::questionsEnabled() ? Product::find($this->productId) : null;
        $questions = $product
            ? ProductQuestion::query()->visible()->forProduct($product)->latest('answered_at')->limit($this->limit + 1)->get()
            : collect();

        return view('dashed-ecommerce-core::livewire.frontend.product-reviews.product-questions', [
            'enabled' => (bool) $product,
            'questions' => $questions->take($this->limit),
            'hasMore' => $questions->count() > $this->limit,
        ]);
    }
}
