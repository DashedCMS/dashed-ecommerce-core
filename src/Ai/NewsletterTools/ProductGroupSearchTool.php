<?php

namespace Dashed\DashedEcommerceCore\Ai\NewsletterTools;

use Illuminate\Database\Eloquent\Builder;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Models\ProductGroup;
use Dashed\DashedNewsletter\Ai\Contracts\SearchTool;
use Dashed\DashedEcommerceCore\Mail\EmailBlocks\ProductsBlock;

/**
 * Productgroepen zoeken. Het model kan hiermee rondkijken zonder per product
 * te hoeven raden, en krijgt per groep meteen een paar zichtbare producten
 * mee zodat het niet voor elke groep een tweede ronde nodig heeft.
 *
 * Een groep zelf komt nooit in de mail: er is geen nieuwsbriefblok voor. Dit
 * is puur een manier om producten te vinden.
 */
class ProductGroupSearchTool implements SearchTool
{
    /** Hoeveel producten er per groep meekomen. Meer maakt het antwoord log. */
    private const PRODUCTEN_PER_GROEP = 5;

    public function name(): string
    {
        return 'searchProductGroups';
    }

    public function description(): string
    {
        return 'Zoek productgroepen van deze website op trefwoord. Geeft per groep een paar openbare producten terug. Gebruik dit om te ontdekken wat er te promoten valt.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'query' => ['type' => 'string', 'description' => 'Zoekterm, bijvoorbeeld een soort product.'],
                'limit' => ['type' => 'integer', 'description' => 'Hoeveel groepen je wilt.'],
            ],
            'required' => ['query'],
        ];
    }

    public function handle(array $input, ?string $siteId): array
    {
        $zoekterm = trim((string) ($input['query'] ?? ''));

        if ($zoekterm === '') {
            return ['results' => []];
        }

        $groepen = self::zichtbaar(ProductGroup::query(), $siteId)
            ->where('name', 'like', '%' . $zoekterm . '%')
            ->limit(ProductSearchTool::limiet($input))
            ->get();

        return [
            'results' => $groepen->map(fn (ProductGroup $groep): array => [
                'id' => $groep->id,
                'name' => $groep->name,
                'url' => rescue(fn () => $groep->getUrl(), null, false),
                'products' => ProductsBlock::visibleQuery($siteId)
                    ->where('product_group_id', $groep->id)
                    ->limit(self::PRODUCTEN_PER_GROEP)
                    ->get()
                    ->map(fn (Product $product): array => [
                        'id' => $product->id,
                        'name' => $product->name,
                        'price' => $product->current_price ?? $product->price,
                    ])
                    ->values()
                    ->all(),
            ])->values()->all(),
        ];
    }

    /**
     * Openbaar, op de juiste site, en binnen het publicatievenster. Handmatig
     * en niet via publicShowable(), om dezelfde reden als bij ProductsBlock en
     * ArticlesBlock: die scope kijkt naar de ingelogde gebruiker en doet voor
     * een beheerder helemaal niets.
     */
    public static function zichtbaar(Builder $query, ?string $siteId): Builder
    {
        $query->where('public', 1)
            ->where(function ($query): void {
                $query->whereNull('start_date')->orWhere('start_date', '<=', now());
            })
            ->where(function ($query): void {
                $query->whereNull('end_date')->orWhere('end_date', '>=', now());
            });

        if ($siteId) {
            $query->whereJsonContains('site_ids', $siteId);
        }

        return $query;
    }
}
