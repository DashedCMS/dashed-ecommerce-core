<?php

namespace Dashed\DashedEcommerceCore\Livewire\Frontend\Products;

use Livewire\Component;
use Dashed\DashedEcommerceCore\Models\ProductGroup;
use Dashed\DashedEcommerceCore\Livewire\Concerns\ProductCartActions;

/**
 * Variant picker shown in a popup for a cross-sell product group with multiple
 * variants. Reuses ProductCartActions (the same machinery as ShowProduct) so the
 * filter selection, variant resolution (findVariation) and addToCart behave
 * exactly like the product detail page — just scoped to the cross-sell group.
 */
class CrossSellVariantPicker extends Component
{
    use ProductCartActions;

    // Alles wat via deze picker in het mandje komt is een cross-sell.
    public ?string $addedVia = 'cross_sell';

    protected $listeners = [
        'setProductExtraCustomValue',
        'addToCart',
    ];

    public function mount(ProductGroup $productGroup): void
    {
        // Mirror ShowProduct::mount() for a group without a preselected variant.
        $this->productGroup = $productGroup;
        $this->productGroup->load([
            'products',
            'products.volumeDiscounts',
            'products.productCategories',
        ]);
        $this->originalProduct = null;
        $this->product = null;

        $this->fillInformation(true);
    }

    public function render()
    {
        return view(config('dashed-core.site_theme', 'dashed') . '.products.cross-sell-variant-picker');
    }
}
