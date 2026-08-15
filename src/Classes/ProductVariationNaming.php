<?php

namespace Dashed\DashedEcommerceCore\Classes;

use Illuminate\Support\Facades\DB;
use Dashed\DashedCore\Classes\Locales;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedTranslations\Models\Translation;
use Dashed\DashedEcommerceCore\Models\ProductGroup;
use Dashed\DashedEcommerceCore\Models\ProductFilterOption;

/**
 * Bepaalt de naam en slug van een variatie: de groepsnaam gevolgd door de
 * gekozen filteropties. Zowel het aanmaken van ontbrekende variaties als de
 * knop "Namen en slugs opnieuw genereren" lopen hier langs, zodat een
 * hergenereerde naam gelijk is aan de naam die bij aanmaken gegeven zou zijn.
 *
 * Uniekheid van de slug staat hier bewust niet in: IsVisitable::saving() plakt
 * er al een teken achter zolang een ander product de slug bezet houdt.
 */
class ProductVariationNaming
{
    /**
     * @var array<int, ProductFilterOption|null>
     */
    protected static array $optionCache = [];

    /**
     * @param  array<int, int>  $optionIds
     */
    public static function name(ProductGroup $productGroup, array $optionIds, string $locale): string
    {
        $name = $productGroup->getTranslation('name', $locale);

        $optionCount = 0;
        foreach ($optionIds as $optionId) {
            $option = self::option($optionId);
            if (! $option) {
                continue;
            }

            $divider = $optionCount === 0
                ? Translation::get('missing-product-variation-name-divider', 'missing-product-variations', '|')
                : Translation::get('missing-product-variation-option-divider', 'missing-product-variations', '|');

            $name .= $divider . ' ' . $option->getTranslation('name', $locale);
            $name = str($name)->replace('  ', ' ')->toString();
            $optionCount++;
        }

        return $name;
    }

    /**
     * @param  array<int, int>  $optionIds
     */
    public static function slug(ProductGroup $productGroup, array $optionIds, string $locale): string
    {
        $slug = $productGroup->getTranslation('slug', $locale);

        foreach ($optionIds as $optionId) {
            $option = self::option($optionId);
            if (! $option) {
                continue;
            }

            $slug .= '-' . $option->getTranslation('name', $locale);
        }

        return str($slug)->slug()->toString();
    }

    /**
     * De variatie-opties van een product, in de volgorde waarin de groep zijn
     * variatiefilters aanhoudt. Filters die niet voor variaties gebruikt worden
     * blijven buiten de naam, net als bij het aanmaken van variaties.
     *
     * @return array<int, int>
     */
    public static function variationOptionIds(ProductGroup $productGroup, Product $product): array
    {
        $optionIds = [];

        foreach ($productGroup->activeProductFiltersForVariations as $productFilter) {
            $optionId = DB::table('dashed__product_filter')
                ->where('product_id', $product->id)
                ->where('product_filter_id', $productFilter->id)
                ->value('product_filter_option_id');

            if ($optionId) {
                $optionIds[] = (int) $optionId;
            }
        }

        return $optionIds;
    }

    /**
     * Geeft alle producten van de groep opnieuw een naam en slug. Talen waarin
     * de groep zelf geen naam heeft blijven ongemoeid: die zouden anders een
     * lege naam krijgen.
     *
     * @param  bool  $regenerateName  laat de naam met rust als dit uit staat
     * @param  bool  $regenerateSlug  laat de slug met rust als dit uit staat
     * @return int  het aantal producten waarvan de naam of slug veranderde
     */
    public static function regenerateForProductGroup(
        ProductGroup $productGroup,
        bool $regenerateName = true,
        bool $regenerateSlug = true
    ): int {
        if (! $regenerateName && ! $regenerateSlug) {
            return 0;
        }

        self::flushOptionCache();

        $locales = collect(Locales::getLocales())
            ->pluck('id')
            ->filter(fn ($locale) => filled($productGroup->getTranslation('name', $locale)))
            ->values();

        if ($locales->isEmpty()) {
            return 0;
        }

        $wanted = [];
        foreach ($productGroup->products()->get() as $product) {
            $optionIds = self::variationOptionIds($productGroup, $product);

            $names = [];
            $slugs = [];
            foreach ($locales as $locale) {
                if ($regenerateName) {
                    $names[$locale] = self::name($productGroup, $optionIds, $locale);
                }

                if ($regenerateSlug) {
                    $slug = self::slug($productGroup, $optionIds, $locale);
                    if ($slug !== '') {
                        $slugs[$locale] = $slug;
                    }
                }
            }

            $changes = false;
            foreach ($names as $locale => $name) {
                if ($product->getTranslation('name', $locale) !== $name) {
                    $changes = true;
                }
            }
            foreach ($slugs as $locale => $slug) {
                if ($product->getTranslation('slug', $locale) !== $slug) {
                    $changes = true;
                }
            }

            if ($changes) {
                $wanted[] = [$product, $names, $slugs];
            }
        }

        if (! $wanted) {
            return 0;
        }

        DB::transaction(function () use ($wanted, $regenerateSlug) {
            // De producten die verschuiven houden elkaars nieuwe slug nog
            // bezet; IsVisitable::saving() zou daar een willekeurig teken
            // achter plakken. Daarom eerst alle betrokken slugs wegzetten,
            // buiten de modelevents om, en daarna pas opslaan.
            if ($regenerateSlug) {
                self::parkSlugs(collect($wanted)->map(fn ($entry) => $entry[0])->all());
            }

            foreach ($wanted as [$product, $names, $slugs]) {
                foreach ($names as $locale => $name) {
                    $product->setTranslation('name', $locale, $name);
                }
                foreach ($slugs as $locale => $slug) {
                    $product->setTranslation('slug', $locale, $slug);
                }

                $product->save();
            }
        });

        return count($wanted);
    }

    /**
     * De optie-cache leeft alleen binnen één ronde: een queue-worker draait
     * lang door en zou anders een hernoemde optie blijven aanhouden.
     */
    public static function flushOptionCache(): void
    {
        self::$optionCache = [];
    }

    /**
     * @param  array<int, Product>  $products
     */
    protected static function parkSlugs(array $products): void
    {
        foreach ($products as $product) {
            $parked = $product->getTranslations('slug');

            foreach ($parked as $locale => $slug) {
                $parked[$locale] = 'regenereren-' . $product->id . '-' . $locale;
            }

            DB::table($product->getTable())
                ->where('id', $product->id)
                ->update(['slug' => json_encode($parked)]);
        }
    }

    protected static function option(int $optionId): ?ProductFilterOption
    {
        if (! array_key_exists($optionId, self::$optionCache)) {
            self::$optionCache[$optionId] = ProductFilterOption::find($optionId);
        }

        return self::$optionCache[$optionId];
    }
}
