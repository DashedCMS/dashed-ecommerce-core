<?php

namespace Dashed\DashedEcommerceCore\Mail\EmailBlocks;

use Filament\Forms\Components\Builder\Block;
use Dashed\DashedCore\Mail\EmailBlocks\EmailBlock;

class OrderTrackAndTraceBlock extends EmailBlock
{
    public static function key(): string
    {
        return 'order-track-and-trace';
    }

    public static function label(): string
    {
        return 'Track & trace';
    }

    public static function filamentBlock(): Block
    {
        return Block::make(self::key())
            ->label(self::label())
            ->icon('heroicon-o-truck')
            ->schema([]);
    }

    public static function render(array $blockData, array $context): string
    {
        $order = $context['order'] ?? null;
        if (! $order) {
            return '';
        }

        $entries = $order->trackAndTraces
            ->map(fn ($t) => [
                'supplier' => trim((string) ($t->delivery_company ?: $t->supplier ?: '')),
                'code' => (string) $t->code,
                'url' => (string) $t->url,
                'expected' => $t->expected_delivery_date?->format('d-m-Y'),
            ])
            ->filter(fn ($t) => $t['code'] !== '' || $t['url'] !== '')
            ->values();

        if ($entries->isEmpty()) {
            return '';
        }

        return view('dashed-ecommerce-core::emails.blocks.order-track-and-trace', [
            'entries' => $entries,
        ])->render();
    }
}
