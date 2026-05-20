<?php

namespace Dashed\DashedEcommerceCore\Livewire\Frontend\Products;

use Livewire\Component;
use Dashed\DashedEcommerceCore\Livewire\Concerns\ProductCartActions;

class ShowProduct extends Component
{
    use ProductCartActions;

    protected $listeners = [
        'setProductExtraCustomValue',
        'addToCart',
    ];

    public function mount($product = null, $productGroup = null)
    {
        $this->productGroup = $productGroup ?: $product->productGroup;

        $this->productGroup->load([
            'products',
//            'products.productFilters',
//            'products.productFilters.productFilterOptions',
//            'activeProductFilters.productFilterOptions',
            'productCategories',
            'products.volumeDiscounts',
            'products.productCategories',
        ]);
        $this->originalProduct = $product ?? null;
        $this->product = $product ?? null;

        $recentlyViewedProductGroups = session('recentlyViewedProducts', []);
        if (in_array($this->productGroup->id, $recentlyViewedProductGroups)) {
            $key = array_search($this->productGroup->id, $recentlyViewedProductGroups);
            unset($recentlyViewedProductGroups[$key]);
        }
        $recentlyViewedProductGroups[] = $this->productGroup->id;
        session(['recentlyViewedProducts' => $recentlyViewedProductGroups]);

        $metaModel = $this->product ?: $this->productGroup;

        $metaDescription = $metaModel->metadata->description ?? '';
        if (! $metaDescription) {
            $metaDescription = $metaModel->productGroup ? ($metaModel->productGroup->metadata->description ?? '') : '';
        }

        seo()->metaData('metaTitle', $metaModel->metadata && $metaModel->metadata->title ? $metaModel->metadata->title : $metaModel->name);
        seo()->metaData('metaDescription', $metaDescription);
        $metaImage = $metaModel->metadata->image ?? '';
        if (! $metaImage) {
            $metaImage = $metaModel->productGroup ? ($metaModel->productGroup->metadata->image ?? '') : '';
        }
        if (! $metaImage) {
            $metaImage = $metaModel->firstImage;
        }
        if ($metaImage) {
            seo()->metaData('metaImage', $metaImage);
        }

        $this->fillInformation(true);
    }

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

    public function rules()
    {
        return [
            'extras.*.value' => ['nullable'],
            'files.*' => ['nullable', 'file'],
        ];
    }

    public function render()
    {
        return view(config('dashed-core.site_theme', 'dashed') . '.products.show-product');
    }
}
