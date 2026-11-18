<?php

namespace Dashed\DashedEcommerceCore\Mail\EmailBlocks;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Builder\Block;
use Dashed\DashedTranslations\Models\Translation;
use Dashed\DashedCore\Mail\EmailBlocks\EmailBlock;
use Dashed\DashedEcommerceCore\Classes\OrderAddOns\EntryPoints;

/**
 * "Nog iets vergeten?" in de orderbevestiging: titel, tot wanneer het kan en een knop naar de ondertekende
 * bijbestelpagina. Rendert alleen als het aanbod open is en de ingang `confirmation_mail` aanstaat.
 */
class OrderAddOnOfferBlock extends EmailBlock
{
    public static function key(): string
    {
        return 'order-add-on-offer';
    }

    public static function label(): string
    {
        return __('Bijbestellen (nog iets vergeten?)');
    }

    public static function filamentBlock(): Block
    {
        return Block::make(self::key())
            ->label(self::label())
            ->icon('heroicon-o-archive-box-arrow-down')
            ->schema([
                TextInput::make('title')
                    ->label(__('Titel'))
                    ->placeholder('Nog iets vergeten? Voeg het toe zonder verzendkosten'),
                TextInput::make('button_label')
                    ->label(__('Knoptekst'))
                    ->placeholder('Bekijk wat er nog bij kan'),
            ]);
    }

    public static function render(array $blockData, array $context): string
    {
        $offer = EntryPoints::confirmationMail($context['order'] ?? null);

        if (! $offer) {
            return '';
        }

        return view('dashed-ecommerce-core::emails.blocks.order-add-on-offer', [
            'title' => filled($blockData['title'] ?? null)
                ? (string) $blockData['title']
                : Translation::get('bevestiging-titel', 'bijbestellen', 'Nog iets vergeten? Voeg het toe zonder verzendkosten'),
            'text' => str_replace(
                [':order:', ':deadline:'],
                [$offer['orderNumber'], $offer['until']],
                Translation::get('bevestiging-uitleg', 'bijbestellen', 'Het gaat mee in de doos van bestelling #:order:. Dat kan :deadline:. Je betaalt alleen de producten.')
            ),
            'buttonLabel' => filled($blockData['button_label'] ?? null)
                ? (string) $blockData['button_label']
                : Translation::get('bedank-knop', 'bijbestellen', 'Bekijk wat er nog bij kan'),
            'url' => $offer['url'],
            'primaryColor' => $context['primaryColor'] ?? '#111827',
        ])->render();
    }
}
