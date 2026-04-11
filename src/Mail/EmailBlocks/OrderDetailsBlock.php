<?php

namespace Dashed\DashedEcommerceCore\Mail\EmailBlocks;

use Filament\Forms\Components\Builder\Block;
use Dashed\DashedCore\Mail\EmailBlocks\EmailBlock;

class OrderDetailsBlock extends EmailBlock
{
    public static function key(): string
    {
        return 'order-details';
    }

    public static function label(): string
    {
        return 'Order gegevens';
    }

    public static function filamentBlock(): Block
    {
        return Block::make(self::key())
            ->label(self::label())
            ->icon('heroicon-o-identification')
            ->schema([]);
    }

    public static function render(array $blockData, array $context): string
    {
        $order = $context['order'] ?? null;
        if (! $order) {
            return '';
        }

        return view('dashed-ecommerce-core::emails.blocks.order-details', [
            'order' => $order,
        ])->render();
    }
}
