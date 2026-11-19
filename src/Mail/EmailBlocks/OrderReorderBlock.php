<?php

namespace Dashed\DashedEcommerceCore\Mail\EmailBlocks;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Builder\Block;
use Dashed\DashedCore\Mail\EmailBlocks\EmailBlock;
use Dashed\DashedEcommerceCore\Services\Reorder\Reorder;

/**
 * Knop "Bestel opnieuw" in een ordermail. Rendert niets als de functie op de
 * site uit staat of de bestelling niet te herhalen is (Bol, creditorder), zodat
 * er nooit een knop zonder werkende link in een mail staat.
 */
class OrderReorderBlock extends EmailBlock
{
    public static function key(): string
    {
        return 'order-reorder';
    }

    public static function label(): string
    {
        return __('Bestel opnieuw');
    }

    public static function filamentBlock(): Block
    {
        return Block::make(self::key())
            ->label(self::label())
            ->icon('heroicon-o-arrow-path')
            ->schema([
                TextInput::make('label')
                    ->label(__('Tekst op de knop'))
                    ->default('Bestel opnieuw'),
                ColorPicker::make('background')->default('#111827'),
                ColorPicker::make('color')->default('#ffffff'),
            ]);
    }

    public static function render(array $blockData, array $context): string
    {
        $order = $context['order'] ?? null;
        $url = $order ? Reorder::url($order) : '';

        if ($url === '') {
            return '';
        }

        return view('dashed-core::emails.blocks.button', [
            'label' => self::substitute($blockData['label'] ?? 'Bestel opnieuw', $context),
            'url' => $url,
            'background' => self::substitute($blockData['background'] ?? '#111827', $context),
            'color' => self::substitute($blockData['color'] ?? '#ffffff', $context),
        ])->render();
    }
}
