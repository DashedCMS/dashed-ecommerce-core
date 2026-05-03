<?php

namespace Dashed\DashedEcommerceCore\Livewire\Frontend\Cart;

use Livewire\Component;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Livewire\Concerns\ProductCartActions;

class AddToCart extends Component
{
    use ProductCartActions;

    public ?string $view = '';

    public function mount(Product $product, ?string $view = '')
    {
        $this->productGroup = $product->productGroup;
        $this->originalProduct = $product ?? null;
        $this->product = $product ?? null;
        $this->view = $view;

        $this->fillInformation(true);
    }

    public function updated()
    {
        $this->fillInformation();

        $previewProductId = $this->product?->id ?? $this->resolvePartialMatchProductId();

        if ($previewProductId) {
            $this->dispatch('cartSuggestionsVariantChanged', productId: $previewProductId);
        }
    }

    /**
     * Wanneer niet alle filters gevuld zijn vindt findVariation() geen volledige match,
     * maar voor de quick-add hero willen we toch al een preview tonen op basis van de
     * gekozen filter-opties zo ver. Pakt het eerste public-showable product in de
     * group dat de huidige (deel-)selectie matcht.
     */
    private function resolvePartialMatchProductId(): ?int
    {
        if (! $this->productGroup) {
            return null;
        }

        $activeOptionIds = collect($this->filters ?? [])
            ->pluck('active')
            ->filter()
            ->values()
            ->all();

        if ($activeOptionIds === []) {
            return $this->originalProduct?->id;
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
                return null;
            }
        }

        return $matchingIds[0] ?? null;
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
        return view(config('dashed-core.site_theme', 'dashed') . '.cart.' . ($this->view ?: 'add-to-cart'));
    }
}
