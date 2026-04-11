<?php

namespace Dashed\DashedEcommerceCore\Mail\EmailBlocks;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Builder\Block;
use Dashed\DashedCore\Mail\EmailBlocks\EmailBlock;

class OrderAddressBlock extends EmailBlock
{
    public static function key(): string
    {
        return 'order-address';
    }

    public static function label(): string
    {
        return 'Order adres';
    }

    public static function filamentBlock(): Block
    {
        return Block::make(self::key())
            ->label(self::label())
            ->icon('heroicon-o-map-pin')
            ->schema([
                Select::make('type')
                    ->label('Type adres')
                    ->options([
                        'shipping' => 'Verzendadres',
                        'invoice' => 'Factuuradres',
                    ])
                    ->default('shipping')
                    ->required(),
            ]);
    }

    public static function render(array $blockData, array $context): string
    {
        $order = $context['order'] ?? null;
        if (! $order) {
            return '';
        }

        $type = $blockData['type'] ?? 'shipping';

        if ($type === 'invoice' && empty($order->invoice_street)) {
            return '';
        }

        return view('dashed-ecommerce-core::emails.blocks.order-address', [
            'order' => $order,
            'type' => $type,
        ])->render();
    }
}
