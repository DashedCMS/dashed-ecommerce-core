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
 * isVariantOf. Geen aggregateRating: er zijn geen productreviews.
 */
class ProductSchema
{
    public const MAX_VARIANTS = 100;

    /** schema.org-eigenschappen voor bekende variatiefilters, op kleine letters. */
    protected const VARIES_BY = [
        'kleur' => 'color',
        'color' => 'color',
        'maat' => 'size',
        'size' => 'size',
        'materiaal' => 'material',
        'material' => 'material',
        'patroon' => 'pattern',
        'pattern' => 'pattern',
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

        if ($group && $variants->count() > self::MAX_VARIANTS) {
            $node->setProperty('isVariantOf', [
                '@type' => 'ProductGroup',
                'productGroupID' => (string) $group->getKey(),
                'name' => (string) $group->name,
            ]);
        }

        return $node;
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
        $variesBy = $filters->map(fn ($filter) => self::variationProperty((string) $filter->name))->values()->all();
        $options = $filters->isEmpty()
            ? collect()
            : ProductFilterOption::query()->whereIn('product_filter_id', $filters->pluck('id'))->get()->keyBy('id');

        $node = Schema::productGroup()
            ->setProperty('productGroupID', (string) $group->getKey())
            ->name((string) $group->name)
            ->url(self::absolute((string) $group->getUrl(app()->getLocale(), false)));

        self::setIfFilled($node, 'description', self::cleanText($group->short_description) ?: self::cleanText($group->description));
        self::setIfFilled($node, 'image', self::imageUrls(is_array($group->images) ? $group->images : []));
        self::setBrand($node, $variants->first());

        if ($variesBy) {
            $node->setProperty('variesBy', $variesBy);
        }

        $node->setProperty('hasVariant', $variants->map(fn (Product $variant) => self::product($variant, $group, $filters, $shared, $options))->all());

        return $node;
    }

    protected static function product(Product $product, ?ProductGroup $group, $variationFilters, array $shared, $options = null): BaseType
    {
        $node = Schema::product()
            ->name((string) $product->name)
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

        return [
            'sellerId' => BusinessProfile::for($siteId, app()->getLocale())['id'] ?? null,
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

        if ($shared['sellerId']) {
            $offer->setProperty('seller', ['@id' => $shared['sellerId']]);
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

            if (isset(self::VARIES_BY[mb_strtolower(trim((string) $filter->name))])) {
                $node->setProperty(self::variationProperty((string) $filter->name), (string) $option->name);
            } else {
                $extra[] = Schema::propertyValue()->name((string) $filter->name)->value((string) $option->name);
            }
        }

        if ($extra) {
            $node->setProperty('additionalProperty', $extra);
        }
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

    protected static function variationProperty(string $filterName): string
    {
        return self::VARIES_BY[mb_strtolower(trim($filterName))] ?? $filterName;
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
