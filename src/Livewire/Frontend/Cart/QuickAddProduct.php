<?php

namespace Dashed\DashedEcommerceCore\Livewire\Frontend\Cart;

use Dashed\DashedEcommerceCore\Livewire\Concerns\ProductCartActions;
use Dashed\DashedEcommerceCore\Models\Product;
use Livewire\Component;

/**
 * Self-contained Livewire-component voor de quick-add modal:
 * hero (image + naam + prijs van de actuele variant) + filter-UI + toevoegen-knop
 * in 1 component. Voorkomt cross-component event-syncing met de parent
 * CartSuggestions, zodat filter-clicks niet via een teleporteerde modal
 * heen-en-weer hoeven.
 */
class QuickAddProduct extends Component
{
    use ProductCartActions;

    public function mount(Product $product): void
    {
        $this->productGroup = $product->productGroup;
        $this->originalProduct = $product;
        $this->product = $product;

        $this->fillInformation(true);
    }

    public function updated(): void
    {
        $this->fillInformation();
    }

    public function rules(): array
    {
        return [
            'extras.*.value' => ['nullable'],
            'files.*' => ['nullable', 'file'],
        ];
    }

    public function render()
    {
        $product = $this->product ?? $this->originalProduct ?? $this->resolveBestPartialMatch();

        return view('dashed-ecommerce-core::livewire.quick-add-product', [
            'heroProduct' => $product,
        ]);
    }

    /**
     * Wanneer findVariation() geen volledige match heeft: pak het eerste
     * publicShowable product dat de huidige (deel-)selectie matcht.
     */
    private function resolveBestPartialMatch(): ?Product
    {
        if (! $this->productGroup) {
            return null;
        }

        $publicProductIds = $this->productGroup->products()
            ->publicShowable()
            ->pluck('id')
            ->all();

        if ($publicProductIds === []) {
            return null;
        }

        $matchingIds = $publicProductIds;

        foreach ($this->filters ?? [] as $filter) {
            if (! ($filter['active'] ?? null)) {
                continue;
            }

            $idsForFilter = \Illuminate\Support\Facades\DB::table('dashed__product_filter')
                ->where('product_filter_id', $filter['id'])
                ->where('product_filter_option_id', $filter['active'])
                ->whereIn('product_id', $matchingIds)
                ->pluck('product_id')
                ->toArray();

            $matchingIds = array_values(array_intersect($matchingIds, $idsForFilter));

            if (empty($matchingIds)) {
                break;
            }
        }

        $resolvedId = $matchingIds[0] ?? $publicProductIds[0] ?? null;

        return $resolvedId ? Product::find($resolvedId) : null;
    }
}
