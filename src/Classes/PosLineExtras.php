<?php

namespace Dashed\DashedEcommerceCore\Classes;

use Illuminate\Support\Collection;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Models\ProductExtra;
use Dashed\DashedEcommerceCore\Models\ProductExtraOption;

/**
 * Product-extra's op een kassaregel: welke extra's een product in de kassa
 * heeft, wat er op een regel gekozen is, wat dat kost en hoe het op de
 * orderregel komt.
 *
 * Een kassaregel draagt:
 *  - 'extra'        [extraId => optieId | [optieId, ...]]
 *  - 'extra_inputs' [extraId => tekst]
 *  - 'extra_prices' [sleutel => prijs incl. btw per stuk], sleutel is het
 *                   optie-id (als string) of 'input-{extraId}'. Ontbreekt de
 *                   sleutel, dan geldt de prijs uit de database.
 *
 * Soorten (ProductExtra::type => kind):
 *  - single, imagePicker, image => 'select' (precies één optie)
 *  - multiple, checkbox         => 'multi'  (nul of meer opties; checkbox
 *                                  heeft er één, aanvinken = kiezen)
 *  - input, textarea            => 'text'   (vrije tekst, meerprijs op de extra)
 *  - file en onbekende soorten  => niet in de kassa (uploaden kan daar niet)
 *
 * Prijzen volgen Product::getShoppingCartItemPrice(): een optie kost de
 * optieprijs plus de meerprijs van zijn extra, per stuk of één keer per regel
 * (calculate_only_1_quantity); een tekst-extra kost de meerprijs van de
 * extra per stuk.
 */
class PosLineExtras
{
    /** @var array<int, Collection> */
    protected array $extrasPerProduct = [];

    /** @var array<int, ?Product> */
    protected array $products = [];

    public static function kindFor(?string $type): ?string
    {
        return match ($type) {
            'single', 'imagePicker', 'image' => 'select',
            'multiple', 'checkbox' => 'multi',
            'input', 'textarea' => 'text',
            default => null,
        };
    }

    public function product(mixed $productId): ?Product
    {
        if (! is_numeric($productId)) {
            return null;
        }

        $productId = (int) $productId;

        if (! array_key_exists($productId, $this->products)) {
            $this->products[$productId] = Product::find($productId);
        }

        return $this->products[$productId];
    }

    /**
     * De extra's die de kassa kan tonen, met hun opties, op volgorde.
     *
     * @return Collection<int, ProductExtra>
     */
    public function available(?Product $product): Collection
    {
        if (! $product) {
            return collect();
        }

        if (! array_key_exists($product->id, $this->extrasPerProduct)) {
            $this->extrasPerProduct[$product->id] = collect($product->allProductExtras() ?? [])
                ->filter(fn (ProductExtra $extra) => self::kindFor($extra->type) !== null)
                ->filter(fn (ProductExtra $extra) => self::kindFor($extra->type) === 'text' || $extra->productExtraOptions->isNotEmpty())
                ->keyBy('id');
        }

        return $this->extrasPerProduct[$product->id];
    }

    public static function optionDbPrice(ProductExtraOption $option): float
    {
        return round((float) $option->priceForUser() + (float) ($option->productExtra?->priceForUser() ?? 0), 2);
    }

    public static function textDbPrice(ProductExtra $extra): float
    {
        return round((float) ($extra->price ?? 0), 2);
    }

    /**
     * Antwoord van de kassa-actie get-product-extras.
     */
    public function describe(Product $product): array
    {
        return $this->available($product)->map(function (ProductExtra $extra) {
            $kind = self::kindFor($extra->type);

            return [
                'id' => (int) $extra->id,
                'name' => (string) $extra->name,
                'type' => (string) $extra->type,
                'kind' => $kind,
                'required' => (bool) $extra->required,
                'price' => $kind === 'text' ? self::textDbPrice($extra) : null,
                'options' => $kind === 'text' ? [] : $extra->productExtraOptions->map(fn (ProductExtraOption $option) => [
                    'id' => (int) $option->id,
                    'name' => (string) $option->value,
                    'price' => self::optionDbPrice($option->setRelation('productExtra', $extra)),
                ])->values()->all(),
            ];
        })->values()->all();
    }

    /**
     * Wat er op de regel gekozen is, met de prijs per stuk die de klant betaalt.
     *
     * @return array<int, array{extra_id: int, option_id: ?int, key: string, name: string, value: string, db_price: float, price: float, price_overridden: bool, once: bool}>
     */
    public function chosen(array $posItem): array
    {
        $prices = (array) ($posItem['extra_prices'] ?? []);
        $extras = $this->available($this->product($posItem['id'] ?? null));
        $chosen = [];

        $selections = PosCartExtras::selections((array) ($posItem['extra'] ?? []));
        $optionIds = collect($selections)->flatten()->all();
        $options = $optionIds
            ? ProductExtraOption::withTrashed()->with('productExtra')->whereIn('id', $optionIds)->get()->keyBy('id')
            : collect();

        foreach ($selections as $extraId => $ids) {
            foreach ($ids as $optionId) {
                $option = $options->get($optionId);
                if (! $option) {
                    continue;
                }

                $extra = $extras->get($extraId) ?? $option->productExtra;
                $dbPrice = self::optionDbPrice($option);
                $key = (string) $optionId;
                $override = $this->priceFrom($prices, $key);

                $chosen[] = [
                    'extra_id' => (int) $extraId,
                    'option_id' => (int) $optionId,
                    'key' => $key,
                    'name' => (string) ($extra?->name ?? ''),
                    'value' => (string) $option->value,
                    'db_price' => $dbPrice,
                    'price' => $override ?? $dbPrice,
                    'price_overridden' => $override !== null,
                    'once' => (bool) $option->calculate_only_1_quantity,
                ];
            }
        }

        $inputs = PosCartExtras::textInputs($posItem);
        $inputExtras = $inputs
            ? ProductExtra::withTrashed()->whereIn('id', array_keys($inputs))->get()->keyBy('id')
            : collect();

        foreach ($inputs as $extraId => $value) {
            $extra = $extras->get($extraId) ?? $inputExtras->get($extraId);
            $dbPrice = $extra ? self::textDbPrice($extra) : 0.0;
            $key = 'input-' . $extraId;
            $override = $this->priceFrom($prices, $key);

            $chosen[] = [
                'extra_id' => (int) $extraId,
                'option_id' => null,
                'key' => $key,
                'name' => (string) ($extra?->name ?? ''),
                'value' => $value,
                'db_price' => $dbPrice,
                'price' => $override ?? $dbPrice,
                'price_overridden' => $override !== null,
                'once' => false,
            ];
        }

        return $chosen;
    }

    /**
     * Namen van verplichte extra's die op de regel nog niet gekozen of
     * ingevuld zijn. Een los ingevoerd product heeft geen extra's.
     *
     * @return array<int, string>
     */
    public function missingRequired(array $posItem): array
    {
        if (! empty($posItem['customProduct'])) {
            return [];
        }

        $selections = PosCartExtras::selections((array) ($posItem['extra'] ?? []));
        $inputs = PosCartExtras::textInputs($posItem);
        $missing = [];

        foreach ($this->available($this->product($posItem['id'] ?? null)) as $extra) {
            if (! $extra->required) {
                continue;
            }

            $filled = self::kindFor($extra->type) === 'text'
                ? isset($inputs[$extra->id])
                : ! empty($selections[$extra->id]);

            if (! $filled) {
                $missing[] = (string) $extra->name;
            }
        }

        return $missing;
    }

    /**
     * Verschil dat aangepaste prijzen op het regeltotaal maken, met dezelfde
     * aantallen als de databaseprijs (per stuk, of één keer bij
     * calculate_only_1_quantity).
     */
    public function overrideDelta(array $posItem, ?array $chosen = null): float
    {
        $qty = max(1, (int) ($posItem['quantity'] ?? 1));
        $delta = 0.0;

        foreach ($chosen ?? $this->chosen($posItem) as $entry) {
            if ($entry['price_overridden']) {
                $delta += ($entry['price'] - $entry['db_price']) * ($entry['once'] ? 1 : $qty);
            }
        }

        return round($delta, 4);
    }

    /**
     * product_extras voor de orderregel, in het formaat van de webshop
     * (Checkout): opties onder hun optie-id, teksten onder
     * 'product-extra-input-{extraId}', met de prijs die de klant betaalt.
     */
    public function orderRows(array $posItem): array
    {
        return array_map(fn (array $entry) => [
            'id' => $entry['option_id'] ?? 'product-extra-input-' . $entry['extra_id'],
            'name' => $entry['name'],
            'value' => $entry['value'],
            'path' => '',
            'price' => $entry['price'],
        ], $this->chosen($posItem));
    }

    /**
     * Velden die de kassa's per regel tonen.
     */
    public function linePayload(array $posItem, string $mode = 'incl'): array
    {
        $isProduct = empty($posItem['customProduct']) && is_numeric($posItem['id'] ?? null);
        $vatRate = (float) ($posItem['vat_rate'] ?? 21);

        return [
            'extras' => array_map(fn (array $entry) => [
                'extra_id' => $entry['extra_id'],
                'option_id' => $entry['option_id'],
                'name' => $entry['name'],
                'value' => $entry['value'],
                'price' => $entry['price'],
                'priceFormatted' => VatDisplay::formatLinePrice((float) $entry['price'], $vatRate, $mode)['primary'],
                'price_overridden' => $entry['price_overridden'],
            ], $isProduct ? $this->chosen($posItem) : []),
            'has_extras_available' => $isProduct && $this->available($this->product($posItem['id']))->isNotEmpty(),
            'has_required_extras_missing' => $isProduct && $this->missingRequired($posItem) !== [],
        ];
    }

    protected function priceFrom(array $prices, string $key): ?float
    {
        $value = $prices[$key] ?? null;

        return is_numeric($value) ? round((float) $value, 2) : null;
    }
}
