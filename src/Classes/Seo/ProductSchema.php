<?php

namespace Dashed\DashedEcommerceCore\Classes\Seo;

use Spatie\SchemaOrg\Schema;
use Spatie\SchemaOrg\BaseType;
use Dashed\DashedCore\Classes\Sites;
use Spatie\SchemaOrg\ItemAvailability;
use Spatie\SchemaOrg\OfferItemCondition;
use Dashed\DashedCore\Models\Customsetting;
use Spatie\SchemaOrg\ReturnFeesEnumeration;
use Spatie\SchemaOrg\ReturnMethodEnumeration;
use Dashed\DashedEcommerceCore\Models\Product;
use Spatie\SchemaOrg\MerchantReturnEnumeration;
use Dashed\DashedEcommerceCore\Classes\Countries;
use Dashed\DashedCore\Classes\Seo\BusinessProfile;
use Dashed\DashedEcommerceCore\Models\ProductGroup;
use Dashed\DashedEcommerceCore\Models\ShippingZone;
use Dashed\DashedEcommerceCore\Models\ShippingMethod;
use Dashed\DashedEcommerceCore\Models\ProductFilterOption;

/**
 * Eén productschema per productpagina: een Product voor een groep met één
 * variant (of een los product), een ProductGroup met hasVariant voor een
 * groep met meerdere, en boven MAX_VARIANTS alleen de gemounte variant met
 * isVariantOf. Geen aggregateRating: er zijn geen productreviews (een app
 * kan ze zelf op dit object zetten via for()).
 *
 * De parent heeft altijd een @id, naam en afbeelding: reviews hangen aan de
 * parent en Search Console meldt anders "Ontbrekend veld 'name' (in
 * '<parent_node>')", zoals bij een groep zonder foto of zonder Duitse naam.
 */
class ProductSchema
{
    public const MAX_VARIANTS = 100;

    /** schema.org-eigenschappen voor bekende variatiefilters, op kleine letters, in elke taal. */
    protected const VARIES_BY = [
        'kleur' => 'color', 'kleuren' => 'color', 'color' => 'color', 'colors' => 'color', 'colour' => 'color',
        'colours' => 'color', 'farbe' => 'color', 'farben' => 'color',
        'maat' => 'size', 'maten' => 'size', 'size' => 'size', 'sizes' => 'size', 'grootte' => 'size',
        'hoogte' => 'size', 'breedte' => 'size', 'afmeting' => 'size', 'afmetingen' => 'size', 'diameter' => 'size',
        'height' => 'size', 'width' => 'size', 'dimensions' => 'size', 'größe' => 'size', 'grösse' => 'size',
        'höhe' => 'size', 'breite' => 'size', 'abmessung' => 'size', 'abmessungen' => 'size',
        'materiaal' => 'material', 'material' => 'material',
        'patroon' => 'pattern', 'pattern' => 'pattern', 'muster' => 'pattern',
    ];

    /** Per verzoek gebouwde schema's, op product-id; een productpagina vraagt hem twee keer. */
    protected const MEMO_ATTRIBUTE = 'dashed-ecommerce-core.product-schemas';

    public static function for(Product $product): BaseType
    {
        $memo = request()->attributes->get(self::MEMO_ATTRIBUTE, []);
        $key = $product->getKey() . ':' . app()->getLocale();
        if (isset($memo[$key])) {
            return $memo[$key];
        }

        $memo[$key] = self::build($product);
        request()->attributes->set(self::MEMO_ATTRIBUTE, $memo);

        return $memo[$key];
    }

    protected static function build(Product $product): BaseType
    {
        $group = $product->productGroup;
        $variants = $group ? self::variants($group, $product) : collect([$product]);
        $shared = self::shared($product);

        if ($group && $variants->count() > 1 && $variants->count() <= self::MAX_VARIANTS) {
            return self::productGroup($group, $variants, $shared);
        }

        $node = self::product($product, $group, null, $shared);
        $node->setProperty('@id', self::nodeId((string) $node->getProperty('url')));

        if ($group && $variants->count() > self::MAX_VARIANTS) {
            $node->setProperty('isVariantOf', array_filter([
                '@type' => 'ProductGroup',
                'productGroupID' => (string) $group->getKey(),
                'name' => self::groupName($group, $variants),
            ]));
        }

        return $node;
    }

    /** {url}#product, zodat reviews ondubbelzinnig aan deze parent hangen. */
    protected static function nodeId(string $url): string
    {
        return strtok($url, '#') . '#product';
    }

    /**
     * De groepsnaam in de huidige taal, anders in de fallback-taal of een
     * andere taal, anders de naam van de eerste variant.
     */
    public static function groupName(ProductGroup $group, $variants = null): string
    {
        $translations = method_exists($group, 'getTranslations') ? $group->getTranslations('name') : [];
        $candidates = [
            $translations[app()->getLocale()] ?? null,
            $translations[(string) config('app.fallback_locale')] ?? null,
            ...array_values($translations),
            $variants ? $variants->first()?->name : null,
        ];

        foreach ($candidates as $candidate) {
            $name = self::cleanText($candidate);
            if ($name !== '') {
                return $name;
            }
        }

        return '';
    }

    /**
     * De gemounte variant vooraan, daarna de andere publieke varianten.
     */
    protected static function variants(ProductGroup $group, Product $mounted)
    {
        $others = $group->products()
            ->where('public', 1)
            ->thisSite()
            ->whereKeyNot($mounted->getKey())
            ->orderBy('id')
            ->with(['productFilters', 'productCategories'])
            ->get();

        $mounted->loadMissing(['productFilters', 'productCategories']);

        return collect([$mounted])->merge($others);
    }

    protected static function productGroup(ProductGroup $group, $variants, array $shared): BaseType
    {
        $filters = $group->activeProductFiltersForVariations;
        $options = $filters->isEmpty()
            ? collect()
            : ProductFilterOption::query()->whereIn('product_filter_id', $filters->pluck('id'))->get()->keyBy('id');
        $groupId = (string) $group->getKey();
        $url = self::absolute((string) $group->getUrl(app()->getLocale(), false));

        $node = Schema::productGroup()
            ->setProperty('@id', self::nodeId($url))
            ->setProperty('productGroupID', $groupId)
            ->url($url);

        self::setIfFilled($node, 'name', self::groupName($group, $variants));
        self::setIfFilled($node, 'description', self::cleanText($group->short_description) ?: self::cleanText($group->description));

        $variantNodes = $variants->map(function (Product $variant) use ($group, $filters, $shared, $options, $groupId) {
            $variantNode = self::product($variant, $group, $filters, $shared, $options);
            $variantNode->setProperty('inProductGroupWithID', $groupId);

            return $variantNode;
        })->all();

        // Zonder groepsfoto: de hoofdfoto van de eerste variant met een foto.
        $image = self::imageUrls(is_array($group->images) ? $group->images : [])
            ?: collect($variantNodes)->map(fn (BaseType $variant) => (array) $variant->getProperty('image'))->first(fn (array $images) => $images !== [], []);
        self::setIfFilled($node, 'image', array_values($image));
        self::setBrand($node, $variants->first());

        if ($variesBy = self::variesBy($variantNodes)) {
            $node->setProperty('variesBy', $variesBy);
        }

        $node->setProperty('hasVariant', $variantNodes);

        return $node;
    }

    /**
     * Alleen eigenschappen die tussen de varianten echt verschillen, als
     * schema.org-URL. Een filter zonder schema.org-eigenschap staat als
     * additionalProperty op de variant en hoort niet in variesBy.
     *
     * @param  list<BaseType>  $variants
     * @return list<string>
     */
    public static function variesBy(array $variants): array
    {
        $variesBy = [];

        foreach (array_unique(self::VARIES_BY) as $property) {
            $values = collect($variants)->map(fn (BaseType $variant) => (string) $variant->getProperty($property))->unique();
            if ($values->count() > 1) {
                $variesBy[] = 'https://schema.org/' . $property;
            }
        }

        return $variesBy;
    }

    protected static function product(Product $product, ?ProductGroup $group, $variationFilters, array $shared, $options = null): BaseType
    {
        $node = Schema::product()
            ->name(self::cleanText($product->name) ?: ($group ? self::groupName($group) : ''))
            ->setProperty('productID', (string) $product->getKey())
            ->url(self::absoluteUrl($product));

        self::setIfFilled($node, 'sku', trim((string) $product->sku));

        if ($gtin = self::gtinProperty($product->ean)) {
            $node->setProperty($gtin[0], $gtin[1]);
        }

        self::setIfFilled($node, 'description', self::description($product));
        self::setIfFilled($node, 'image', self::images($product));
        self::setBrand($node, $product);

        if ($variationFilters) {
            self::setVariationValues($node, $product, $variationFilters, $options ?? collect());
        }

        if ($offer = self::offer($product, $shared)) {
            $node->setProperty('offers', $offer);
        }

        return $node;
    }

    /**
     * Wat alle varianten delen: verkoper, verzending per zone, retourbeleid.
     * Eén keer per schema berekend.
     */
    protected static function shared(Product $product): array
    {
        $siteId = (string) Sites::getActive();
        $shipping = self::shippingDetails($product);
        $countries = collect($shipping)
            ->flatMap(fn (BaseType $detail) => $detail->toArray()['shippingDestination']['addressCountry'] ?? [])
            ->unique()
            ->values()
            ->all();

        $profile = BusinessProfile::for($siteId, app()->getLocale());

        return [
            'sellerId' => $profile['id'] ?? null,
            'sellerName' => trim((string) ($profile['name'] ?? '')),
            'shipping' => $shipping,
            'returnPolicy' => self::returnPolicy($countries, $siteId),
            'currency' => (string) config('app.currency', 'EUR'),
        ];
    }

    /**
     * De prijs is de opgeslagen publieksprijs inclusief btw. Bewust niet
     * priceForUser(): die valt terug op de ingelogde bezoeker (prijsgroep,
     * ex-btw-weergave) en het schema is voor iedereen hetzelfde.
     */
    public static function offer(Product $product, array $shared): ?BaseType
    {
        $price = $product->getRawOriginal('current_price');
        if ($price === null || $price === '') {
            // Prijs op aanvraag: geen Offer, anders staat het product als gratis te boek.
            return null;
        }

        $offer = Schema::offer()
            ->price(number_format((float) $price, 2, '.', ''))
            ->priceCurrency($shared['currency'])
            ->availability(self::availability($product))
            ->itemCondition(OfferItemCondition::NewCondition)
            ->url(self::absoluteUrl($product));

        // Met type en naam: alleen een @id gaf bij verkopersvermeldingen "Ontbrekend veld 'name'".
        if ($shared['sellerId'] || ($shared['sellerName'] ?? '') !== '') {
            $offer->setProperty('seller', array_filter([
                '@type' => 'Organization',
                '@id' => $shared['sellerId'] ?: null,
                'name' => ($shared['sellerName'] ?? '') ?: null,
            ]));
        }
        if ($shared['shipping']) {
            $offer->setProperty('shippingDetails', $shared['shipping']);
        }
        if ($shared['returnPolicy']) {
            $offer->setProperty('hasMerchantReturnPolicy', $shared['returnPolicy']);
        }

        return $offer;
    }

    public static function availability(Product $product): string
    {
        if ($product->hasDirectSellableStock()) {
            return ItemAvailability::InStock;
        }
        if ($product->isPreorderable()) {
            return ItemAvailability::PreOrder;
        }
        if ($product->outOfStockSellable()) {
            return ItemAvailability::BackOrder;
        }

        return ItemAvailability::OutOfStock;
    }

    /**
     * Per verzendzone het basistarief van de goedkoopste methode (zonder
     * toeslag per verzendklasse: die vraagt een winkelwagenregel) en de
     * levertijd van het product. Methodes die dit product of zijn groep
     * uitsluiten tellen niet mee; een zone zonder bekend land of methode
     * valt weg.
     *
     * @return list<BaseType>
     */
    public static function shippingDetails(Product $product): array
    {
        $details = [];
        $maxDays = max(1, (int) ($product->expectedDeliveryInDays() ?: 3));
        $currency = (string) config('app.currency', 'EUR');
        $zones = ShippingZone::query()->where('site_id', Sites::getActive())->with('shippingMethods')->get();
        $price = (float) $product->getRawOriginal('current_price');

        foreach ($zones as $zone) {
            $countries = collect((array) $zone->zones)
                ->map(fn ($name) => Countries::getCountryIsoCode($name))
                ->filter()
                ->map(fn ($code) => strtoupper((string) $code))
                ->unique()
                ->values()
                ->all();

            if (! $countries) {
                continue;
            }

            // Zoals de checkout: alleen methodes die bij deze orderwaarde (één stuk)
            // aangeboden worden, geen afhalen, en geen methode die dit product
            // of zijn groep uitsluit.
            $rate = $zone->shippingMethods
                ->reject(fn (ShippingMethod $method) => $method->sort === 'take_away'
                    || ((float) ($method->minimum_order_value ?: 0) > $price)
                    || ($method->maximum_order_value !== null && (float) $method->maximum_order_value > 0 && (float) $method->maximum_order_value < $price)
                    || $method->disabledProducts()->whereKey($product->getKey())->exists()
                    || ($product->product_group_id && $method->disabledProductGroups()->whereKey($product->product_group_id)->exists()))
                ->map(fn (ShippingMethod $method) => $method->costsForCart($zone->id, []))
                ->filter(fn ($costs) => $costs !== null)
                ->min();

            if ($rate === null) {
                continue;
            }

            $details[] = Schema::offerShippingDetails()
                ->shippingDestination(Schema::definedRegion()->addressCountry($countries))
                ->shippingRate(Schema::monetaryAmount()->value(number_format((float) $rate, 2, '.', ''))->currency($currency))
                ->deliveryTime(Schema::shippingDeliveryTime()->transitTime(
                    Schema::quantitativeValue()->minValue(1)->maxValue($maxDays)->unitCode('DAY')
                ));
        }

        return $details;
    }

    public static function returnPolicy(array $countryCodes, ?string $siteId = null): ?BaseType
    {
        if (! $countryCodes) {
            return null;
        }

        $days = (int) (Customsetting::get('returns_auto_accept_max_days', $siteId) ?: 14);

        return Schema::merchantReturnPolicy()
            ->returnPolicyCategory(MerchantReturnEnumeration::MerchantReturnFiniteReturnWindow)
            ->merchantReturnDays($days)
            ->returnMethod(ReturnMethodEnumeration::ReturnByMail)
            ->returnFees(static::returnFees($siteId))
            ->applicableCountry($countryCodes);
    }

    /**
     * Wie de retourzending betaalt, voor Google's verkopersvermeldingen.
     * Standaard de klant; "free" alleen als de shop het retourlabel betaalt.
     */
    public static function returnFees(?string $siteId = null): string
    {
        return Customsetting::get('returns_schema_return_fees', $siteId) === 'free'
            ? ReturnFeesEnumeration::FreeReturn
            : ReturnFeesEnumeration::ReturnFeesCustomerResponsibility;
    }

    protected static function setVariationValues(BaseType $node, Product $product, $filters, $options): void
    {
        $chosen = $product->productFilters->keyBy('id');
        $extra = [];

        foreach ($filters as $filter) {
            $optionId = $chosen[$filter->id]->pivot->product_filter_option_id ?? null;
            $option = $optionId ? ($options[$optionId] ?? ProductFilterOption::find($optionId)) : null;
            if (! $option) {
                continue;
            }

            $value = self::translated($option);
            if ($value === '') {
                continue;
            }

            if ($property = self::variationProperty($filter)) {
                // Twee filters op dezelfde eigenschap (Hoogte en Maat): samen.
                $existing = (string) $node->getProperty($property);
                $node->setProperty($property, $existing !== '' && $existing !== $value ? $existing . ' / ' . $value : $value);
            } else {
                $extra[] = Schema::propertyValue()->name(self::translated($filter))->value($value);
            }
        }

        if ($extra) {
            $node->setProperty('additionalProperty', $extra);
        }
    }

    /** De naam in de huidige taal, anders in een andere taal: een Duitse filter heeft vaak geen naam. */
    protected static function translated(object $model): string
    {
        $translations = method_exists($model, 'getTranslations') ? $model->getTranslations('name') : [];

        foreach ([$translations[app()->getLocale()] ?? null, $translations[(string) config('app.fallback_locale')] ?? null, ...array_values($translations), $model->name ?? null] as $candidate) {
            $text = is_scalar($candidate) ? trim(preg_replace('/\s+/u', ' ', (string) $candidate)) : '';
            if ($text !== '') {
                return $text;
            }
        }

        return '';
    }

    /**
     * Product::getUrl() heeft als tweede argument $forceOwnUrl, geen $native:
     * bij only_show_parent_product komt er de relatieve groeps-URL uit.
     */
    protected static function absoluteUrl(Product $product): string
    {
        return self::absolute((string) $product->getUrl(app()->getLocale(), false));
    }

    protected static function absolute(string $url): string
    {
        return preg_match('#^https?://#', $url) ? $url : url($url);
    }

    /** De schema.org-eigenschap van een filter, herkend op zijn naam in elke taal; null als er geen is. */
    public static function variationProperty(object|string $filter): ?string
    {
        $names = is_string($filter)
            ? [$filter]
            : [...(method_exists($filter, 'getTranslations') ? array_values($filter->getTranslations('name')) : []), $filter->name ?? null];

        foreach ($names as $name) {
            if (is_scalar($name) && isset(self::VARIES_BY[$key = mb_strtolower(trim((string) $name))])) {
                return self::VARIES_BY[$key];
            }
        }

        return null;
    }

    /**
     * @return array{0: string, 1: string}|null
     */
    public static function gtinProperty(?string $ean): ?array
    {
        $ean = trim((string) $ean);

        if (! preg_match('/^\d+$/', $ean)) {
            return null;
        }

        return match (strlen($ean)) {
            8 => ['gtin8', $ean],
            13 => ['gtin13', $ean],
            14 => ['gtin14', $ean],
            default => null,
        };
    }

    /**
     * Dezelfde keten als Gs1MetaResolver: product, dan de eerste categorie
     * met een waarde, dan de site-instelling.
     */
    public static function brandName(Product $product): ?string
    {
        $value = trim((string) ($product->gs1_brand ?? ''));

        if ($value === '') {
            foreach ($product->productCategories as $category) {
                $value = trim((string) ($category->gs1_brand ?? ''));
                if ($value !== '') {
                    break;
                }
            }
        }

        if ($value === '') {
            $value = trim((string) Customsetting::get('gs1_default_brand', Sites::getActive(), ''));
        }

        return $value === '' ? null : $value;
    }

    protected static function setBrand(BaseType $node, ?Product $product): void
    {
        if ($product && ($brand = self::brandName($product))) {
            $node->setProperty('brand', Schema::brand()->name($brand));
        }
    }

    public static function description(Product $product): string
    {
        $group = $product->productGroup;

        foreach ([$product->short_description, $group?->short_description, $product->description, $group?->description] as $candidate) {
            $text = self::cleanText($candidate);
            if ($text !== '') {
                return $text;
            }
        }

        return '';
    }

    /**
     * @return list<string>
     */
    public static function images(Product $product): array
    {
        $ids = is_array($product->images) && $product->images ? $product->images : ($product->productGroup?->images ?? []);

        return self::imageUrls(is_array($ids) ? $ids : []);
    }

    protected static function imageUrls(array $ids): array
    {
        $urls = [];
        foreach ($ids as $id) {
            $media = $id ? mediaHelper()->getSingleMedia($id, 'original') : null;
            $url = is_object($media) ? ($media->url ?? null) : (is_string($media) && $media !== '' ? $media : null);
            if ($url) {
                $urls[] = (string) $url;
            }
        }

        return array_values(array_unique($urls));
    }

    /**
     * Een korte omschrijving is HTML of platte tekst; een omschrijving is een
     * TipTap-document (als JSON-string of array) en gaat eerst door
     * cms()->convertToHtml(), zoals de oude microdata deed.
     */
    protected static function cleanText(mixed $value): string
    {
        if (is_array($value)) {
            $value = rescue(fn () => cms()->convertToHtml($value), '', false);
        } elseif (is_string($value) && str_starts_with(ltrim($value), '{')) {
            $decoded = json_decode($value, true);
            if (is_array($decoded) && ($decoded['type'] ?? null) === 'doc') {
                $value = rescue(fn () => cms()->convertToHtml($decoded), '', false);
            }
        }

        if (! is_string($value)) {
            return '';
        }

        return trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5)));
    }

    protected static function setIfFilled(BaseType $node, string $property, mixed $value): void
    {
        if ($value === '' || $value === null || $value === []) {
            return;
        }

        $node->setProperty($property, $value);
    }
}
