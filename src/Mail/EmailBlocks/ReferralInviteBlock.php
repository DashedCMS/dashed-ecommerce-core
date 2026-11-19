<?php

namespace Dashed\DashedEcommerceCore\Mail\EmailBlocks;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Builder\Block;
use Dashed\DashedCore\Mail\EmailBlocks\EmailBlock;
use Dashed\DashedEcommerceCore\Services\Referrals\ReferralProgram;

/**
 * De eigen verwijslink van de klant in een ordermail. Rendert niets als het
 * verwijsprogramma op de site van de bestelling uit staat.
 */
class ReferralInviteBlock extends EmailBlock
{
    public static function key(): string
    {
        return 'referral-invite';
    }

    public static function label(): string
    {
        return __('Verwijslink');
    }

    public static function filamentBlock(): Block
    {
        return Block::make(self::key())
            ->label(self::label())
            ->icon('heroicon-o-user-plus')
            ->schema([
                Textarea::make('text')
                    ->label(__('Tekst boven de knop'))
                    ->default('Tevreden? Deel je eigen link met vrienden: zij krijgen korting op hun eerste bestelling en jij een bedankje.'),
                TextInput::make('label')
                    ->label(__('Tekst op de knop'))
                    ->default('Deel je link'),
                ColorPicker::make('background')->default('#111827'),
                ColorPicker::make('color')->default('#ffffff'),
            ]);
    }

    public static function render(array $blockData, array $context): string
    {
        $order = $context['order'] ?? null;
        $url = $order ? ReferralProgram::urlForOrder($order) : '';

        if ($url === '') {
            return '';
        }

        $text = trim((string) self::substitute($blockData['text'] ?? '', $context));

        return ($text !== '' ? view('dashed-core::emails.blocks.text', ['body' => e($text)])->render() : '')
            . view('dashed-core::emails.blocks.button', [
                'label' => self::substitute($blockData['label'] ?? 'Deel je link', $context),
                'url' => $url,
                'background' => self::substitute($blockData['background'] ?? '#111827', $context),
                'color' => self::substitute($blockData['color'] ?? '#ffffff', $context),
            ])->render();
    }
}
