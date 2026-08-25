<?php

namespace Dashed\DashedEcommerceCore\Ai\NewsletterTools;

use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedNewsletter\Ai\Contracts\SearchTool;
use Dashed\DashedEcommerceCore\Models\ProductCategory;
use Dashed\DashedEcommerceCore\Mail\EmailBlocks\ProductsBlock;

/**
 * Categorieen zoeken. Zelfde gedachte als de productgroepen: een manier om
 * producten te vinden, geen blok in de mail.
 */
class CategorySearchTool implements SearchTool
{
    private const PRODUCTEN_PER_CATEGORIE = 5;

    public function name(): string
    {
        return 'searchProductCategories';
    }

    public function description(): string
    {
        return 'Zoek productcategorieen van deze website op trefwoord. Geeft per categorie een paar openbare producten terug.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'query' => ['type' => 'string', 'description' => 'Zoekterm, bijvoorbeeld een categorienaam.'],
                'limit' => ['type' => 'integer', 'description' => 'Hoeveel categorieen je wilt.'],
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

        $categorieen = ProductGroupSearchTool::zichtbaar(ProductCategory::query(), $siteId)
            ->where('name', 'like', '%' . $zoekterm . '%')
            ->limit(ProductSearchTool::limiet($input))
            ->get();

        return [
            'results' => $categorieen->map(fn (ProductCategory $categorie): array => [
                'id' => $categorie->id,
                'name' => $categorie->name,
                'url' => rescue(fn () => $categorie->getUrl(), null, false),
                'products' => ProductsBlock::visibleQuery($siteId)
                    ->whereIn('dashed__products.id', $categorie->products()->pluck('dashed__products.id')->all())
                    ->limit(self::PRODUCTEN_PER_CATEGORIE)
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
}
