<?php

namespace Dashed\DashedEcommerceCore\Mail\EmailBlocks;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Dashed\DashedEcommerceCore\Models\Product;
use Filament\Forms\Components\Builder\Block;
use Dashed\DashedCore\Mail\EmailBlocks\EmailBlock;

class AutoProductsBlock extends EmailBlock
{
    public static function key(): string
    {
        return 'auto-products';
    }

    public static function label(): string
    {
        return __('Automatische productselectie');
    }

    public static function contexts(): array
    {
        return [self::CONTEXT_NEWSLETTER];
    }

    public static function filamentBlock(): Block
    {
        return Block::make(self::key())
            ->label(self::label())
            ->icon('heroicon-o-sparkles')
            ->schema([
                Select::make('selection')
                    ->label(__('Wat moet erin'))
                    ->options([
                        'newest' => __('Nieuwste producten'),
                        'best_sold' => __('Best verkocht'),
                        'sale' => __('In de aanbieding'),
                    ])
                    ->default('newest')
                    ->required()
                    ->helperText(__('Wordt gevuld op het moment van verzenden, niet nu.')),
                TextInput::make('limit')->label(__('Aantal producten'))->numeric()->default(4),
                Select::make('columns')->label(__('Aantal kolommen'))->options([1 => '1', 2 => '2', 3 => '3', 4 => '4'])->default(2),
            ]);
    }

    public static function render(array $blockData, array $context): string
    {
        $limit = max(1, min(12, (int) ($blockData['limit'] ?? 4)));

        $query = Product::public();

        // De selectie wordt op het moment van verzenden bepaald, één keer voor
        // de hele ronde. Zie CampaignRenderer: rendert de code per ontvanger,
        // dan draait deze query net zo vaak als er ontvangers zijn.
        match ($blockData['selection'] ?? 'newest') {
            'best_sold' => $query->orderByDesc('total_purchases'),
            'sale' => $query->whereNotNull('discount_price')->orderByDesc('created_at'),
            default => $query->orderByDesc('created_at'),
        };

        return ProductsBlock::renderProducts(
            $query->limit($limit)->get(),
            (int) ($blockData['columns'] ?? 2),
            $context
        );
    }
}
