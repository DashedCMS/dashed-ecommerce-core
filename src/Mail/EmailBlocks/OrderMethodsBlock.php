<?php

namespace Dashed\DashedEcommerceCore\Mail\EmailBlocks;

use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Builder\Block;
use Dashed\DashedCore\Mail\EmailBlocks\EmailBlock;

class OrderMethodsBlock extends EmailBlock
{
    public static function key(): string
    {
        return 'order-methods';
    }

    public static function label(): string
    {
        return 'Verzend- en betaalmethode';
    }

    public static function filamentBlock(): Block
    {
        return Block::make(self::key())
            ->label(self::label())
            ->icon('heroicon-o-truck')
            ->schema([
                Toggle::make('show_shipping')->label('Verzendmethode tonen')->default(true),
                Toggle::make('show_payment')->label('Betaalmethode tonen')->default(true),
                Toggle::make('show_instructions')->label('Betaalinstructies tonen')->default(true),
            ]);
    }

    public static function render(array $blockData, array $context): string
    {
        $order = $context['order'] ?? null;
        if (! $order) {
            return '';
        }

        return view('dashed-ecommerce-core::emails.blocks.order-methods', [
            'order' => $order,
            'showShipping' => (bool) ($blockData['show_shipping'] ?? true),
            'showPayment' => (bool) ($blockData['show_payment'] ?? true),
            'showInstructions' => (bool) ($blockData['show_instructions'] ?? true),
        ])->render();
    }
}
