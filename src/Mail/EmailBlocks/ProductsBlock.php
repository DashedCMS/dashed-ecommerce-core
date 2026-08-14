<?php

namespace Dashed\DashedEcommerceCore\Mail\EmailBlocks;

use Filament\Forms\Components\Select;
use Dashed\DashedEcommerceCore\Models\Product;
use Filament\Forms\Components\Builder\Block;
use Dashed\DashedCore\Mail\EmailBlocks\EmailBlock;

class ProductsBlock extends EmailBlock
{
    public static function key(): string
    {
        return 'products';
    }

    public static function label(): string
    {
        return __('Producten');
    }

    public static function contexts(): array
    {
        return [self::CONTEXT_NEWSLETTER];
    }

    public static function filamentBlock(): Block
    {
        return Block::make(self::key())
            ->label(self::label())
            ->icon('heroicon-o-shopping-bag')
            ->schema([
                Select::make('product_ids')
                    ->label(__('Producten'))
                    ->multiple()
                    ->searchable()
                    ->getSearchResultsUsing(fn (string $search): array => Product::public()
                        ->where('name', 'like', "%{$search}%")
                        ->limit(20)
                        ->pluck('name', 'id')
                        ->all())
                    ->getOptionLabelsUsing(fn (array $values): array => Product::whereIn('id', $values)->pluck('name', 'id')->all())
                    ->required(),
                Select::make('columns')
                    ->label(__('Aantal kolommen'))
                    ->options([1 => '1', 2 => '2', 3 => '3', 4 => '4'])
                    ->default(2),
            ]);
    }

    public static function render(array $blockData, array $context): string
    {
        $ids = array_filter((array) ($blockData['product_ids'] ?? []));

        if ($ids === []) {
            return '';
        }

        // public() erbij: een product dat op de site verborgen is, hoort ook
        // niet in een mail te staan. En sortBy houdt de volgorde aan die de
        // redacteur koos, want whereIn geeft die niet terug.
        $products = Product::public()
            ->whereIn('id', $ids)
            ->get()
            ->sortBy(fn (Product $product): int => array_search($product->id, $ids, true))
            ->values();

        return self::renderProducts($products, (int) ($blockData['columns'] ?? 2), $context);
    }

    /**
     * Gedeelde weergave voor ProductsBlock en AutoProductsBlock: beide zetten
     * een lijst producten om naar hetzelfde raster, met de hand gekozen of
     * automatisch gevuld hoort er hetzelfde uit te zien.
     *
     * @param  \Illuminate\Support\Collection<int, \Dashed\DashedEcommerceCore\Models\Product>  $products
     * @param  array<string, mixed>  $context
     */
    public static function renderProducts($products, int $columns, array $context): string
    {
        return view('dashed-ecommerce-core::emails.blocks.products', [
            'products' => $products->map(function (Product $product): array {
                // getSingleMedia() geeft '' terug als er geen afbeelding is, en
                // een object met een url-eigenschap als die er wel is (geen
                // getFullUrl()-methode, dat is een ander mediapakket).
                $media = mediaHelper()->getSingleMedia($product->firstImage);

                return [
                    'name' => $product->name,
                    'url' => $product->getUrl(),
                    'image' => is_object($media) ? ($media->url ?? '') : '',
                    'price' => $product->current_price ?? $product->price,
                ];
            })->all(),
            'columns' => max(1, min(4, $columns)),
            'primaryColor' => $context['primaryColor'] ?? '#111827',
            'textColor' => $context['textColor'] ?? '#ffffff',
        ])->render();
    }
}
