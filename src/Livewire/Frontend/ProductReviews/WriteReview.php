<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Livewire\Frontend\ProductReviews;

use Livewire\Component;
use Livewire\Attributes\Locked;
use Dashed\DashedEcommerceCore\Models\OrderProduct;
use Dashed\DashedEcommerceCore\Models\ProductReview;
use Dashed\DashedEcommerceCore\Services\ProductReviews\ProductReviewSubmitter;

/**
 * Het reviewformulier achter de ondertekende link. De handtekening wordt
 * gecontroleerd op de paginalading (ProductReviewController); de
 * Livewire-verzoeken daarna dragen hem niet, dus de orderregel staat
 * #[Locked] en is vanuit de browser niet te wijzigen.
 */
class WriteReview extends Component
{
    #[Locked]
    public int $orderProductId;

    public int $rating = 0;

    public string $title = '';

    public string $body = '';

    public string $name = '';

    public bool $submitted = false;

    public bool $alreadyReviewed = false;

    public function mount(int $orderProductId): void
    {
        $this->orderProductId = $orderProductId;
        $line = $this->line();

        $this->name = (string) ($line?->order?->first_name ?? '');
        $this->alreadyReviewed = ProductReview::query()->where('order_product_id', $orderProductId)->exists();
    }

    protected function line(): ?OrderProduct
    {
        return OrderProduct::with(['order', 'product'])->find($this->orderProductId);
    }

    protected function rules(): array
    {
        return [
            'rating' => ['required', 'integer', 'between:1,5'],
            'title' => ['nullable', 'string', 'max:120'],
            'body' => ['required', 'string', 'min:3', 'max:5000'],
            'name' => ['required', 'string', 'max:80'],
        ];
    }

    public function setRating(int $rating): void
    {
        $this->rating = max(1, min(5, $rating));
    }

    public function submit(): void
    {
        if ($this->alreadyReviewed || $this->submitted) {
            return;
        }

        $this->validate();

        $line = $this->line();
        if (! $line || ! $line->order) {
            return;
        }

        $review = ProductReviewSubmitter::fromOrderProduct($line, [
            'rating' => $this->rating,
            'title' => $this->title,
            'body' => $this->body,
            'name' => $this->name,
        ]);

        $review ? $this->submitted = true : $this->alreadyReviewed = true;
    }

    public function render()
    {
        return view('dashed-ecommerce-core::livewire.frontend.product-reviews.write-review', [
            'line' => $this->line(),
        ]);
    }
}
