<?php

namespace Dashed\DashedEcommerceCore\Classes;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Models\ProductGroup;
use Dashed\DashedEcommerceCore\Models\ProductCharacteristic;

/**
 * Het Bol-titelsjabloon van een productgroep of product: tekst met
 * plaatshouders als :kleur:, die per variant uit de feedattributen gevuld
 * worden. De feed, het voorbeeld in het CMS en de controle op een
 * AI-voorstel vullen allemaal hier in, zodat ze dezelfde titel zien.
 */
class BolTitleTemplate
{
    /**
     * Een plaatshouder die na het invullen nog in de tekst staat. De naam moet
     * met een letter beginnen, mag daarna alles bevatten behalve een dubbele
     * punt of een nieuwe regel (met een plafond van 60 tekens), en mag niet
     * midden in een woord of getal staan, zodat "1:2:3" en "Tijd:10:30"
     * blijven staan.
     */
    public const PLACEHOLDER_PATTERN = '/(?<![\p{L}\p{N}]):\p{L}[^:\r\n]{0,60}?:(?![\p{L}\p{N}])/u';

    /**
     * @param  array<string, mixed>  $attributes  naam => waarde, zoals ProductFeedAttributes::forProduct()
     */
    public static function render(string $template, array $attributes): string
    {
        $title = $template;

        foreach ($attributes as $name => $value) {
            $title = str_replace(':' . Str::lower((string) $name) . ':', is_scalar($value) ? (string) $value : '', $title);
        }

        return self::tidy($title);
    }

    /**
     * Haalt onvulbare plaatshouders weg en ruimt op wat dan overblijft: dubbele
     * spaties, een spatie voor een komma, twee scheidingstekens na elkaar en
     * een scheidingsteken aan het begin of eind.
     */
    public static function tidy(string $title): string
    {
        $title = preg_replace(self::PLACEHOLDER_PATTERN, '', $title);
        $title = preg_replace('/\s+/u', ' ', $title);
        $title = preg_replace('/\s+,/u', ',', $title);
        $title = preg_replace('/([-,\/])(\s*[-,\/])+/u', '$1', $title);
        $title = preg_replace('/^[\s,\/-]+|[\s,\/-]+$/u', '', $title);

        return trim($title);
    }

    /**
     * De plaatshouders die bij deze groep kunnen, gesleuteld op de schrijfwijze
     * in het sjabloon (kleine letters). Namen komen in de actieve taal, net als
     * in de feed. Van filters alleen de opties die producten van deze groep
     * echt hebben: een filter als Kleur is gedeeld en heeft er vaak tientallen.
     *
     * @return array<string, array{name: string, kind: string, options: list<string>}>
     */
    public static function variables(ProductGroup $group): array
    {
        $variables = [];
        $productIds = $group->products()->pluck('id');

        $usedOptionIds = DB::table('dashed__product_filter')
            ->whereIn('product_id', $productIds)
            ->pluck('product_filter_option_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $group->loadMissing('activeProductFilters.productFilterOptions');

        foreach ($group->activeProductFilters as $filter) {
            $name = (string) $filter->name;
            if ($name === '') {
                continue;
            }

            $variables[Str::lower($name)] = [
                'name' => $name,
                'kind' => $filter->pivot->use_for_variations ? 'filter' : 'group_filter',
                'options' => $filter->productFilterOptions
                    ->filter(fn ($option) => in_array((int) $option->id, $usedOptionIds, true))
                    ->pluck('name')
                    ->filter()
                    ->values()
                    ->all(),
            ];
        }

        foreach ($group->allCharacteristicsWithoutFilters() as $characteristic) {
            $name = (string) $characteristic['name'];
            if ($name === '') {
                continue;
            }

            $variables[Str::lower($name)] ??= [
                'name' => $name,
                'kind' => 'group_characteristic',
                'options' => [(string) $characteristic['value']],
            ];
        }

        $rows = ProductCharacteristic::query()
            ->whereIn('product_id', $productIds)
            ->with('productCharacteristic')
            ->get();

        foreach ($rows as $row) {
            $name = (string) ($row->productCharacteristic?->name ?? '');
            $value = (string) ($row->value ?? '');
            if ($name === '' || $value === '') {
                continue;
            }

            $key = Str::lower($name);
            $variables[$key] ??= ['name' => $name, 'kind' => 'product_characteristic', 'options' => []];

            if ($variables[$key]['kind'] === 'product_characteristic'
                && count($variables[$key]['options']) < 10
                && ! in_array($value, $variables[$key]['options'], true)) {
                $variables[$key]['options'][] = $value;
            }
        }

        return $variables;
    }

    /**
     * De feedattributen van de eerste producten van de groep, voor het voorbeeld
     * en de lengtecontrole. simpleFilters() wordt één keer per groep gelezen.
     *
     * @return list<array<string, mixed>>
     */
    public static function attributeSets(ProductGroup $group, int $limit = 200): array
    {
        $group->loadMissing('activeProductFilters.productFilterOptions');
        $simpleFilters = $group->simpleFilters();

        return $group->products()
            ->with('productFilters')
            ->orderBy('order')
            ->orderBy('id')
            ->limit($limit)
            ->get()
            ->map(function (Product $product) use ($group, $simpleFilters) {
                $product->setRelation('productGroup', $group);

                return ProductFeedAttributes::forProduct(
                    $product,
                    ProductFeedAttributes::activeFilters($product, $simpleFilters)
                );
            })
            ->values()
            ->all();
    }

    /**
     * @param  list<array<string, mixed>>  $sets
     * @return list<string>
     */
    public static function renderForSets(string $template, array $sets): array
    {
        if (trim($template) === '') {
            return [];
        }

        return array_map(fn (array $attributes) => self::render($template, $attributes), $sets);
    }
}
