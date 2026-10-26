<?php

namespace Dashed\DashedEcommerceCore\Classes;

use Illuminate\Support\Facades\DB;
use Dashed\DashedCore\Classes\Locales;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Models\ProductGroup;

/**
 * Welke producten en productgroepen iets missen: foto's, teksten, vertalingen
 * en de data die feeds (Bol, Channable, Google) nodig hebben. Leest alles in
 * een paar ruwe queries, zodat ook duizenden varianten in één keer gaan.
 *
 * Een product erft foto's, teksten, SEO en categorieën van zijn groep: een
 * veld telt pas als ontbrekend als het op product én groep leeg is. Alleen
 * "geen eigen foto's" kijkt naar het product zelf, want een variant zonder
 * eigen foto toont de foto's van de groep (vaak in een andere kleur).
 */
class ProductCompleteness
{
    public const CATEGORY_IMAGES = 'fotos';

    public const CATEGORY_TEXTS = 'teksten';

    public const CATEGORY_TRANSLATIONS = 'vertalingen';

    public const CATEGORY_FEED = 'feed';

    /**
     * @return array<string, string>
     */
    public static function categories(): array
    {
        return [
            self::CATEGORY_IMAGES => __("Foto's"),
            self::CATEGORY_TEXTS => __('Teksten'),
            self::CATEGORY_TRANSLATIONS => __('Vertalingen'),
            self::CATEGORY_FEED => __('Product- en feeddata'),
        ];
    }

    /**
     * Alle controles: sleutel => [label, categorie]. De vertaalcontroles
     * bestaan per taal behalve de eerste (de hoofdtaal).
     *
     * @param  array<int, string>  $otherLocales
     * @return array<string, array{label: string, category: string}>
     */
    public static function checks(array $otherLocales): array
    {
        $checks = [
            'no_images' => [__("Geen foto's"), self::CATEGORY_IMAGES],
            'no_own_images' => [__("Geen eigen foto's"), self::CATEGORY_IMAGES],
            'no_name' => [__('Geen naam'), self::CATEGORY_TEXTS],
            'no_description' => [__('Geen beschrijving'), self::CATEGORY_TEXTS],
            'no_short_description' => [__('Geen korte beschrijving'), self::CATEGORY_TEXTS],
            'no_seo_title' => [__('Geen SEO-titel'), self::CATEGORY_TEXTS],
            'no_seo_description' => [__('Geen SEO-omschrijving'), self::CATEGORY_TEXTS],
        ];

        foreach ($otherLocales as $locale) {
            $code = strtoupper($locale);
            $checks["name_{$locale}"] = [__('Naam ontbreekt') . " ({$code})", self::CATEGORY_TRANSLATIONS];
            $checks["slug_{$locale}"] = [__('Slug ontbreekt') . " ({$code})", self::CATEGORY_TRANSLATIONS];
            $checks["description_{$locale}"] = [__('Beschrijving niet vertaald') . " ({$code})", self::CATEGORY_TRANSLATIONS];
        }

        $checks += [
            'no_price' => [__('Geen prijs'), self::CATEGORY_FEED],
            'no_purchase_price' => [__('Geen inkoopprijs'), self::CATEGORY_FEED],
            'no_sku' => [__('Geen SKU'), self::CATEGORY_FEED],
            'no_ean' => [__('Geen EAN'), self::CATEGORY_FEED],
            'no_weight' => [__('Geen gewicht'), self::CATEGORY_FEED],
            'no_dimensions' => [__('Geen afmetingen'), self::CATEGORY_FEED],
            'no_category' => [__('Geen categorie'), self::CATEGORY_FEED],
            'missing_filter' => [__('Variatiefilter zonder waarde'), self::CATEGORY_FEED],
        ];

        return array_map(fn (array $check) => ['label' => $check[0], 'category' => $check[1]], $checks);
    }

    /**
     * Wat een product mist.
     *
     * @param  array<string, mixed>  $product  ruwe kolommen; translatables als JSON-tekst of array
     * @param  array<string, mixed>|null  $group  ruwe kolommen van de groep
     * @param  array{
     *     product_meta?: array{title: mixed, description: mixed}|null,
     *     group_meta?: array{title: mixed, description: mixed}|null,
     *     has_category?: bool,
     *     missing_filter?: bool,
     *     group_product_count?: int,
     * }  $context
     * @param  array<int, string>  $otherLocales
     * @return array<int, string> controlesleutels
     */
    public static function evaluateProduct(array $product, ?array $group, array $context, string $mainLocale, array $otherLocales): array
    {
        $group ??= [];
        $missing = [];

        $ownImages = self::hasImages($product['images'] ?? null);
        if (! $ownImages && ! self::hasImages($group['images'] ?? null)) {
            $missing[] = 'no_images';
        } elseif (! $ownImages && ($context['group_product_count'] ?? 1) > 1) {
            $missing[] = 'no_own_images';
        }

        $missing = array_merge($missing, self::textChecks(
            [$product, $group],
            [$context['product_meta'] ?? null, $context['group_meta'] ?? null],
            $mainLocale,
            $otherLocales,
            $product,
        ));

        if (! self::hasText(self::translation($product['name'] ?? null, $mainLocale))) {
            $missing[] = 'no_name';
        }

        if (! ($product['is_bundle'] ?? false) && (float) ($product['price'] ?? 0) <= 0) {
            $missing[] = 'no_price';
        }
        if ((float) ($product['purchase_price'] ?? 0) <= 0) {
            $missing[] = 'no_purchase_price';
        }
        if (self::isBlank($product['sku'] ?? null)) {
            $missing[] = 'no_sku';
        }
        if (self::isBlank($product['ean'] ?? null)) {
            $missing[] = 'no_ean';
        }
        if ((float) ($product['weight'] ?? 0) <= 0) {
            $missing[] = 'no_weight';
        }
        foreach (['length', 'width', 'height'] as $dimension) {
            if ((float) ($product[$dimension] ?? 0) <= 0) {
                $missing[] = 'no_dimensions';

                break;
            }
        }
        if (! ($context['has_category'] ?? true)) {
            $missing[] = 'no_category';
        }
        if ($context['missing_filter'] ?? false) {
            $missing[] = 'missing_filter';
        }

        return $missing;
    }

    /**
     * Wat een productgroep zelf mist (los van zijn producten).
     *
     * @param  array<string, mixed>  $group
     * @param  array{group_meta?: array{title: mixed, description: mixed}|null, has_category?: bool}  $context
     * @param  array<int, string>  $otherLocales
     * @return array<int, string>
     */
    public static function evaluateGroup(array $group, array $context, string $mainLocale, array $otherLocales): array
    {
        $missing = [];

        if (! self::hasImages($group['images'] ?? null)) {
            $missing[] = 'no_images';
        }

        $missing = array_merge($missing, self::textChecks(
            [$group],
            [$context['group_meta'] ?? null],
            $mainLocale,
            $otherLocales,
            $group,
        ));

        if (! ($context['has_category'] ?? true)) {
            $missing[] = 'no_category';
        }

        return $missing;
    }

    /**
     * Teksten en vertalingen. De eerste bron die een tekst heeft telt
     * (product, dan groep); naam en slug kijken alleen naar het eigen model.
     *
     * @param  array<int, array<string, mixed>>  $sources
     * @param  array<int, array{title: mixed, description: mixed}|null>  $metas
     * @param  array<int, string>  $otherLocales
     * @param  array<string, mixed>  $own
     * @return array<int, string>
     */
    private static function textChecks(array $sources, array $metas, string $mainLocale, array $otherLocales, array $own): array
    {
        $missing = [];

        $hasField = function (string $field, string $locale) use ($sources): bool {
            foreach ($sources as $source) {
                if (self::hasText(self::translation($source[$field] ?? null, $locale))) {
                    return true;
                }
            }

            return false;
        };

        $hasMeta = function (string $field) use ($metas, $mainLocale): bool {
            foreach ($metas as $meta) {
                if ($meta && self::hasText(self::translation($meta[$field] ?? null, $mainLocale))) {
                    return true;
                }
            }

            return false;
        };

        $hasDescription = $hasField('description', $mainLocale);
        if (! $hasDescription) {
            $missing[] = 'no_description';
        }
        if (! $hasField('short_description', $mainLocale)) {
            $missing[] = 'no_short_description';
        }
        if (! $hasMeta('title')) {
            $missing[] = 'no_seo_title';
        }
        if (! $hasMeta('description')) {
            $missing[] = 'no_seo_description';
        }

        foreach ($otherLocales as $locale) {
            if (! self::hasText(self::translation($own['name'] ?? null, $locale))) {
                $missing[] = "name_{$locale}";
            }
            if (! self::hasText(self::translation($own['slug'] ?? null, $locale))) {
                $missing[] = "slug_{$locale}";
            }
            // Zonder tekst in de hoofdtaal valt er niets te vertalen; dat
            // meldt no_description al.
            if ($hasDescription && ! $hasField('description', $locale)) {
                $missing[] = "description_{$locale}";
            }
        }

        return $missing;
    }

    /**
     * Of een waarde leesbare tekst bevat: HTML zonder tekst en een leeg
     * TipTap-document (alleen een lege paragraaf) tellen als leeg.
     */
    public static function hasText(mixed $value): bool
    {
        if (is_array($value)) {
            if (isset($value['text']) && is_string($value['text']) && trim($value['text']) !== '') {
                return true;
            }
            if (in_array($value['type'] ?? null, ['image', 'video'], true)) {
                return true;
            }
            foreach ($value as $child) {
                if (is_array($child) && self::hasText($child)) {
                    return true;
                }
            }

            return false;
        }

        if (! is_string($value)) {
            return $value !== null && $value !== false && trim((string) $value) !== '';
        }

        $value = trim($value);
        if ($value === '') {
            return false;
        }

        if ($value[0] === '{' && is_array($decoded = json_decode($value, true))) {
            return self::hasText($decoded);
        }

        $text = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(str_replace("\u{00A0}", ' ', $text)) !== '';
    }

    /**
     * De waarde van een vertaalbaar veld in één taal. Een kolom die geen
     * vertaal-JSON is (oude data) geldt voor elke taal.
     */
    public static function translation(mixed $value, string $locale): mixed
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (! is_array($decoded)) {
                return $value;
            }
            $value = $decoded;
        }

        if (! is_array($value)) {
            return $value;
        }

        // Een TipTap-document dat direct in de kolom staat, zonder talen.
        if (isset($value['type'])) {
            return $value;
        }

        return $value[$locale] ?? null;
    }

    public static function hasImages(mixed $images): bool
    {
        if (is_string($images)) {
            $images = json_decode($images, true);
        }

        return is_array($images) && count(array_filter($images, fn ($image) => ! self::isBlank($image))) > 0;
    }

    private static function isBlank(mixed $value): bool
    {
        return $value === null || (is_string($value) && trim($value) === '') || $value === [];
    }

    /**
     * @return array{0: string, 1: array<int, string>} hoofdtaal en de overige talen
     */
    public static function locales(): array
    {
        $locales = array_keys(Locales::getLocalesArray());
        $main = array_shift($locales) ?? config('app.locale');

        return [$main, array_values($locales)];
    }

    /**
     * Rijen voor de groeps- en productweergave.
     *
     * @return array{groups: array<int, array<string, mixed>>, products: array<int, array<string, mixed>>}
     */
    public static function report(): array
    {
        [$mainLocale, $otherLocales] = self::locales();

        $textColumns = ['name', 'slug', 'short_description', 'description', 'images', 'public'];

        $groups = DB::table('dashed__product_groups')
            ->whereNull('deleted_at')
            ->get(array_merge(['id'], $textColumns))
            ->keyBy('id')
            ->map(fn ($row) => (array) $row)
            ->all();

        $products = DB::table('dashed__products')
            ->whereNull('deleted_at')
            ->get(array_merge(['id', 'product_group_id', 'sku', 'ean', 'price', 'purchase_price', 'weight', 'length', 'width', 'height', 'is_bundle'], $textColumns))
            ->map(fn ($row) => (array) $row)
            ->all();

        $metas = [];
        DB::table('dashed__metadata')
            ->whereIn('metadatable_type', [Product::class, ProductGroup::class])
            ->get(['metadatable_type', 'metadatable_id', 'title', 'description'])
            ->each(function ($row) use (&$metas) {
                $metas[$row->metadatable_type][$row->metadatable_id] = ['title' => $row->title, 'description' => $row->description];
            });

        $productsWithCategory = [];
        $groupsWithCategory = [];
        DB::table('dashed__product_category')
            ->get(['product_id', 'product_group_id'])
            ->each(function ($row) use (&$productsWithCategory, &$groupsWithCategory) {
                if ($row->product_id) {
                    $productsWithCategory[$row->product_id] = true;
                }
                if ($row->product_group_id) {
                    $groupsWithCategory[$row->product_group_id] = true;
                }
            });

        $variationFilters = [];
        DB::table('dashed__active_product_filter')
            ->where('use_for_variations', 1)
            ->whereNotNull('product_group_id')
            ->get(['product_group_id', 'product_filter_id'])
            ->each(function ($row) use (&$variationFilters) {
                $variationFilters[$row->product_group_id][$row->product_filter_id] = true;
            });

        $productFilters = [];
        if ($variationFilters) {
            DB::table('dashed__product_filter')
                ->whereNotNull('product_filter_option_id')
                ->get(['product_id', 'product_filter_id'])
                ->each(function ($row) use (&$productFilters) {
                    $productFilters[$row->product_id][$row->product_filter_id] = true;
                });
        }

        $productCounts = [];
        foreach ($products as $product) {
            $productCounts[$product['product_group_id']] = ($productCounts[$product['product_group_id']] ?? 0) + 1;
        }

        $productRows = [];
        $productsPerGroup = [];
        foreach ($products as $product) {
            $groupId = $product['product_group_id'];
            $group = $groups[$groupId] ?? null;

            $missingFilter = false;
            foreach (array_keys($variationFilters[$groupId] ?? []) as $filterId) {
                if (! isset($productFilters[$product['id']][$filterId])) {
                    $missingFilter = true;

                    break;
                }
            }

            $missing = self::evaluateProduct($product, $group, [
                'product_meta' => $metas[Product::class][$product['id']] ?? null,
                'group_meta' => $metas[ProductGroup::class][$groupId] ?? null,
                'has_category' => isset($productsWithCategory[$product['id']]) || isset($groupsWithCategory[$groupId]),
                'missing_filter' => $missingFilter,
                'group_product_count' => $productCounts[$groupId] ?? 1,
            ], $mainLocale, $otherLocales);

            $row = [
                '__key' => (string) $product['id'],
                'id' => (int) $product['id'],
                'name' => self::plain(self::translation($product['name'], $mainLocale)) ?: '#' . $product['id'],
                'group_id' => $groupId ? (int) $groupId : null,
                'group_name' => $group ? self::plain(self::translation($group['name'], $mainLocale)) : null,
                'sku' => $product['sku'],
                'ean' => $product['ean'],
                'public' => (bool) $product['public'] && (! $group || (bool) $group['public']),
                'missing' => $missing,
                'missing_count' => count($missing),
            ];

            $productRows[] = $row;
            if ($groupId) {
                $productsPerGroup[$groupId][] = $row;
            }
        }

        $groupRows = [];
        foreach ($groups as $groupId => $group) {
            $own = self::evaluateGroup($group, [
                'group_meta' => $metas[ProductGroup::class][$groupId] ?? null,
                'has_category' => isset($groupsWithCategory[$groupId]),
            ], $mainLocale, $otherLocales);

            $perCheck = [];
            $productsWithIssues = 0;
            foreach ($productsPerGroup[$groupId] ?? [] as $row) {
                if ($row['missing']) {
                    $productsWithIssues++;
                }
                foreach ($row['missing'] as $key) {
                    $perCheck[$key] = ($perCheck[$key] ?? 0) + 1;
                }
            }
            arsort($perCheck);

            $groupRows[] = [
                '__key' => (string) $groupId,
                'id' => (int) $groupId,
                'name' => self::plain(self::translation($group['name'], $mainLocale)) ?: '#' . $groupId,
                'public' => (bool) $group['public'],
                'missing' => $own,
                'product_count' => count($productsPerGroup[$groupId] ?? []),
                'products_with_issues' => $productsWithIssues,
                'product_issues' => $perCheck,
                'missing_count' => count($own) + array_sum($perCheck),
            ];
        }

        return ['groups' => $groupRows, 'products' => $productRows];
    }

    /**
     * Onder deze score staat een product in het filter "onder 80%".
     */
    public const THRESHOLD = 80;

    /**
     * Controles die het publiceren van een product tegenhouden.
     */
    public const BLOCKING = ['no_name', 'no_price'];

    /** @var \WeakMap<Product, array<int, string>>|null */
    private static ?\WeakMap $memo = null;

    /**
     * Wat één product mist, met dezelfde controles als report(), maar met
     * een handvol queries voor alleen dit product. Per model-object
     * onthouden: de balk en de publicatiecheck lezen het in één verzoek
     * meerdere keren.
     *
     * @return array<int, string>
     */
    public static function forProduct(Product $product, bool $fresh = false): array
    {
        self::$memo ??= new \WeakMap();

        if (! $fresh && isset(self::$memo[$product])) {
            return self::$memo[$product];
        }

        [$mainLocale, $otherLocales] = self::locales();
        $groupId = $product->product_group_id;

        $group = $groupId
            ? (array) (DB::table('dashed__product_groups')->where('id', $groupId)->first(['id', 'name', 'slug', 'short_description', 'description', 'images', 'public']) ?? [])
            : [];

        $metas = DB::table('dashed__metadata')
            ->where(fn ($q) => $q
                ->where(fn ($q) => $q->where('metadatable_type', Product::class)->where('metadatable_id', $product->id))
                ->orWhere(fn ($q) => $q->where('metadatable_type', ProductGroup::class)->where('metadatable_id', $groupId ?? 0)))
            ->get(['metadatable_type', 'title', 'description'])
            ->keyBy('metadatable_type')
            ->map(fn ($row) => ['title' => $row->title, 'description' => $row->description]);

        $hasCategory = DB::table('dashed__product_category')
            ->where(fn ($q) => $q->where('product_id', $product->id)->when($groupId, fn ($q) => $q->orWhere('product_group_id', $groupId)))
            ->exists();

        $missingFilter = false;
        if ($groupId) {
            $variationFilters = DB::table('dashed__active_product_filter')
                ->where('use_for_variations', 1)
                ->where('product_group_id', $groupId)
                ->pluck('product_filter_id')
                ->all();

            if ($variationFilters) {
                $filled = DB::table('dashed__product_filter')
                    ->where('product_id', $product->id)
                    ->whereNotNull('product_filter_option_id')
                    ->whereIn('product_filter_id', $variationFilters)
                    ->distinct()
                    ->count('product_filter_id');
                $missingFilter = $filled < count(array_unique($variationFilters));
            }
        }

        $raw = $product->getAttributes();

        return self::$memo[$product] = self::evaluateProduct($raw, $group ?: null, [
            'product_meta' => $metas[Product::class] ?? null,
            'group_meta' => $metas[ProductGroup::class] ?? null,
            'has_category' => $hasCategory,
            'missing_filter' => $missingFilter,
            'group_product_count' => $groupId ? Product::query()->where('product_group_id', $groupId)->count() : 1,
        ], $mainLocale, $otherLocales);
    }

    /**
     * Percentage uit een lijst ontbrekende controles: elke controle telt
     * even zwaar. Dezelfde som als Completeness::check() met de regels uit
     * registerRules(), zodat de kolom en de balk hetzelfde zeggen.
     *
     * @param  array<int, string>  $missing
     */
    public static function score(array $missing): int
    {
        $checks = array_keys(self::checks(self::locales()[1]));
        $total = count($checks);

        if ($total === 0) {
            return 100;
        }

        return (int) floor(($total - count(array_intersect($checks, array_unique($missing)))) / $total * 100);
    }

    /**
     * Zet `completeness` op één product, zonder events: dit is afgeleide
     * informatie en hoort geen saved-hooks (feeds, caches) af te laten gaan.
     */
    public static function store(Product $product): int
    {
        $score = self::score(self::forProduct($product, fresh: true));

        Product::query()->whereKey($product->id)->update(['completeness' => $score]);
        $product->setRawAttributes(array_merge($product->getAttributes(), ['completeness' => $score]), true);

        return $score;
    }

    /**
     * De hele catalogus in één keer, uit report(): een update per
     * voorkomend percentage in plaats van een per product.
     */
    public static function storeAll(): int
    {
        $perScore = [];
        foreach (self::report()['products'] as $row) {
            $perScore[self::score($row['missing'])][] = $row['id'];
        }

        foreach ($perScore as $score => $ids) {
            foreach (array_chunk($ids, 1000) as $chunk) {
                Product::query()->whereIn('id', $chunk)->update(['completeness' => $score]);
            }
        }

        return array_sum(array_map('count', $perScore));
    }

    /**
     * Meldt de controles aan bij het register in dashed-core, voor de balk op
     * de bewerkpagina. Achter class_exists: een oudere dashed-core kent het
     * register niet.
     */
    public static function registerRules(): void
    {
        if (! class_exists(\Dashed\DashedCore\Classes\Completeness\Completeness::class)) {
            return;
        }

        foreach (self::checks(self::locales()[1]) as $key => $check) {
            \Dashed\DashedCore\Classes\Completeness\Completeness::register(
                Product::class,
                $key,
                $check['label'],
                fn (Product $product) => ! in_array($key, self::forProduct($product), true),
                blocking: in_array($key, self::BLOCKING, true),
            );
        }
    }

    private static function plain(mixed $value): string
    {
        return is_string($value) ? trim(strip_tags($value)) : '';
    }
}
