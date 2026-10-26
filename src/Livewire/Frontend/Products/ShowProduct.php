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

        static::applyMetaData($metaModel);

        $this->fillInformation(true);

        // Op een groeps-URL is de variant pas na fillInformation() bekend.
        if ($this->product instanceof \Dashed\DashedEcommerceCore\Models\Product) {
            static::pushProductSchema($this->product);
        }
    }

    /**
     * Eén Product- of ProductGroup-schema per productpagina, onder de sleutel
     * 'product' zodat de head het precies één keer rendert.
     */
    public static function pushProductSchema(\Dashed\DashedEcommerceCore\Models\Product $product): void
    {
        $schemas = seo()->metaData('schemas');

        $schemas = is_array($schemas) ? $schemas : [];

        // Winkelreviews (dashed-core zet ze op het OnlineStore-blok) gaan over de
        // organisatie zelf: die tellen niet voor reviewfragmenten, en op een
        // productpagina meldde Search Console ze als "Ontbrekend veld 'name' (in
        // '<parent_node>')". Hier horen alleen reviews van het product.
        if (($schemas['organization'] ?? null) instanceof \Spatie\SchemaOrg\BaseType) {
            $schemas['organization']->offsetUnset('aggregateRating');
            $schemas['organization']->offsetUnset('review');
        }

        seo()->metaData('schemas', array_merge($schemas, [
            'product' => \Dashed\DashedEcommerceCore\Classes\Seo\ProductSchema::for($product),
        ]));
    }

    /**
     * Meta-gegevens van een product- of productgroeppagina. Statisch zodat het
     * zonder het thema te renderen te toetsen is.
     */
    public static function applyMetaData(object $metaModel): void
    {
        $metaDescription = $metaModel->metadata->description ?? '';
        if (! $metaDescription) {
            $metaDescription = $metaModel->productGroup ? ($metaModel->productGroup->metadata->description ?? '') : '';
        }

        seo()->metaData('metaTitle', $metaModel->metadata && $metaModel->metadata->title ? $metaModel->metadata->title : $metaModel->name);
        seo()->metaData('metaDescription', $metaDescription);
        seo()->set('ogType', 'product');

        if ($metaModel instanceof \Dashed\DashedEcommerceCore\Models\Product) {
            static::pushProductSchema($metaModel);
        }


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
