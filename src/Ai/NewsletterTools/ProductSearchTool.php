<?php

namespace Dashed\DashedEcommerceCore\Ai\NewsletterTools;

use Illuminate\Database\Eloquent\Builder;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedNewsletter\Ai\Contracts\SearchTool;
use Dashed\DashedEcommerceCore\Mail\EmailBlocks\ProductsBlock;

/**
 * Producten zoeken voor fase 1 van de nieuwsbriefgenerator.
 *
 * De grenzen zitten in deze query en niet in de instructie aan het model.
 * ProductsBlock::visibleQuery() is dezelfde bron die het productblok gebruikt
 * bij het renderen van de mail, dus wat het model mag zien is per definitie
 * wat er in de mail terecht mag komen.
 *
 * Bewust niet publicShowable(): die scope is een no-op voor een ingelogde
 * beheerder met rol admin, en deze code draait altijd in een beheerderssessie.
 * Verborgen producten zouden dan gewoon in de resultaten komen.
 */
class ProductSearchTool implements SearchTool
{
    public function name(): string
    {
        return 'searchProducts';
    }

    public function description(): string
    {
        return 'Zoek openbare producten van deze website op trefwoord. Geeft naam, prijs, afbeelding en link terug. Gebruik dit om te kiezen welke producten in de nieuwsbrief komen.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'query' => ['type' => 'string', 'description' => 'Zoekterm, bijvoorbeeld een productnaam of een soort product.'],
                'min_price' => ['type' => 'number', 'description' => 'Alleen producten vanaf deze prijs.'],
                'max_price' => ['type' => 'number', 'description' => 'Alleen producten tot en met deze prijs.'],
                'limit' => ['type' => 'integer', 'description' => 'Hoeveel resultaten je wilt. Er komen er nooit meer dan het maximum van de site.'],
            ],
            'required' => ['query'],
        ];
    }

    public function handle(array $input, ?string $siteId): array
    {
        $zoekterm = trim((string) ($input['query'] ?? ''));

        // Zonder zoekterm zou dit de hele catalogus zijn, en dat is precies
        // wat de begrenzing moet voorkomen.
        if ($zoekterm === '') {
            return ['results' => []];
        }

        $query = ProductsBlock::visibleQuery($siteId)
            ->where(function (Builder $query) use ($zoekterm): void {
                $query->where('name', 'like', '%' . $zoekterm . '%')
                    ->orWhere('short_description', 'like', '%' . $zoekterm . '%');
            });

        if (isset($input['min_price']) && is_numeric($input['min_price'])) {
            $query->where('price', '>=', (float) $input['min_price']);
        }

        if (isset($input['max_price']) && is_numeric($input['max_price'])) {
            $query->where('price', '<=', (float) $input['max_price']);
        }

        return [
            'results' => $query->limit(self::limiet($input))->get()
                ->map(fn (Product $product): array => self::samenvatting($product))
                ->values()
                ->all(),
        ];
    }

    /**
     * De grens uit de config wint altijd van wat het model vraagt.
     *
     * @param array<string, mixed> $input
     */
    public static function limiet(array $input): int
    {
        $max = (int) config('dashed-newsletter.ai.max_search_results', 25);
        $gevraagd = (int) ($input['limit'] ?? $max);

        return max(1, min($gevraagd, $max));
    }

    /**
     * Wat het model van een product te zien krijgt. Klein gehouden: het model
     * ziet alleen de naam, de prijs en een korte omschrijving, dus het hoort
     * niet te schrijven over materiaal of levertijd.
     *
     * @return array<string, mixed>
     */
    public static function samenvatting(Product $product): array
    {
        return [
            'id' => $product->id,
            'name' => $product->name,
            'short_description' => $product->short_description,
            'price' => $product->current_price ?? $product->price,
            'url' => rescue(fn () => $product->getUrl(), null, false),
            'image' => rescue(fn () => mediaHelper()->getSingleMedia($product->firstImage, 'small')?->url, null, false),
        ];
    }
}
