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

    protected $listeners = [
        'setProductExtraCustomValue',
        'addToCart',
    ];

    public function mount(ProductGroup $productGroup): void
    {
        // Alles wat via deze picker in het mandje komt is een cross-sell.
        // Wordt hier gezet i.p.v. als property-default, omdat PHP 8.4 een
        // class-property met andere default dan de trait (ProductCartActions::$addedVia = null)
        // als incompatibel beschouwt en een fatal error geeft.
        $this->addedVia = 'cross_sell';

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

    // Spiegelt ShowProduct::updated() — zonder deze hook werkt een filterwissel
    // (wire:model.live op filters.*.active) in de popup niet: het filter wordt
    // wel gezet, maar de variant/prijs worden nooit opnieuw berekend.
    public function updated($name, $value)
    {
        if (str($name)->contains(['qty', 'quantity'])) {
            return;
        }

        // Extras/files wijzigen het product niet - alleen prijzen herberekenen
        if (str($name)->startsWith(['extras', 'files'])) {
            $this->calculateCurrentPrices();

            return;
        }

        // Wanneer de gebruiker zelf een filter wijzigt, beschouwen we die
        // keuze als leidend. autoResolveFilterConflicts laat dat filter dan
        // ongemoeid en past alleen de overige filters aan.
        $lockedFilterKey = null;
        if (preg_match('/^filters\.(\d+)\.active$/', (string) $name, $m)) {
            $lockedFilterKey = (int) $m[1];
        }

        $this->fillInformation(false, $lockedFilterKey);
    }

    public function render()
    {
        return view(config('dashed-core.site_theme', 'dashed') . '.products.cross-sell-variant-picker');
    }
}
