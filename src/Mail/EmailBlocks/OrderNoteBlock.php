<?php

namespace Dashed\DashedEcommerceCore\Mail\EmailBlocks;

use Filament\Forms\Components\Builder\Block;
use Dashed\DashedCore\Mail\EmailBlocks\EmailBlock;

class OrderNoteBlock extends EmailBlock
{
    public static function key(): string
    {
        return 'order-note';
    }

    public static function label(): string
    {
        return 'Klantnotitie';
    }

    public static function filamentBlock(): Block
    {
        return Block::make(self::key())
            ->label(self::label())
            ->icon('heroicon-o-chat-bubble-left-ellipsis')
            ->schema([]);
    }

    public static function render(array $blockData, array $context): string
    {
        $order = $context['order'] ?? null;
        if (! $order || empty($order->note)) {
            return '';
        }

        return view('dashed-ecommerce-core::emails.blocks.order-note', [
            'order' => $order,
        ])->render();
    }
}
