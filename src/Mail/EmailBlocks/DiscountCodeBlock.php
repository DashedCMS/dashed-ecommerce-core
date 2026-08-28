<?php

namespace Dashed\DashedEcommerceCore\Mail\EmailBlocks;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Builder\Block;
use Filament\Schemas\Components\Utilities\Get;
use Dashed\DashedCore\Mail\EmailBlocks\EmailBlock;
use Dashed\DashedEcommerceCore\Models\DiscountCode;

class DiscountCodeBlock extends EmailBlock
{
    public static function key(): string
    {
        return 'discount-code';
    }

    public static function label(): string
    {
        return __('Kortingscode');
    }

    public static function contexts(): array
    {
        return [self::CONTEXT_NEWSLETTER];
    }

    public static function filamentBlock(): Block
    {
        return Block::make(self::key())
            ->label(self::label())
            ->icon('heroicon-o-ticket')
            ->schema([
                Select::make('discount_code_id')
                    ->label(__('Bestaande kortingscode'))
                    // Drie stappen omhoog en niet twee. Het statePath van een
                    // blokschema in een Builder is data.blocks.<uuid>.data, en
                    // elke ../ stript er één segment af. Met ../../ kom je uit
                    // op data.blocks.site_id, wat niet bestaat, en dan doet de
                    // filter stilzwijgend niets. Met ../../../ kom je op
                    // data.site_id, en dat is het veld dat je zoekt.
                    //
                    // Zonder deze filter toont de keuzelijst kortingscodes van
                    // elke site, en dan kan een redacteur voor site A per
                    // ongeluk een code van site B kiezen. $get() geeft null
                    // terug als het pad niet bestaat, en dan blijft de lijst
                    // ongefilterd: niet erger dan vóór deze reparatie.
                    ->options(fn (Get $get): array => DiscountCode::query()
                        ->when(
                            $get('../../../site_id'),
                            fn ($query, $siteId) => $query->whereJsonContains('site_ids', $siteId)
                        )
                        ->pluck('code', 'id')
                        ->all())
                    ->searchable(),
                TextInput::make('code')
                    ->label(__('Code (als deze niet in de webshop staat)')),
                TextInput::make('description')
                    ->label(__('Omschrijving')),
            ]);
    }

    public static function render(array $blockData, array $context): string
    {
        // Ook hier op site filteren en niet alleen in de keuzelijst: een code
        // die ooit gekozen is toen de filter nog niet klopte, of een campagne
        // die naar een andere site is verplaatst, zou anders alsnog een code
        // van de verkeerde site tonen.
        $siteId = $context['siteId'] ?? null;

        $discountCode = ! empty($blockData['discount_code_id'])
            ? DiscountCode::query()
                ->when($siteId, fn ($query, $site) => $query->whereJsonContains('site_ids', $site))
                ->find($blockData['discount_code_id'])
            : null;

        // Terugval op het losse tekstveld: niet elke code in een nieuwsbrief
        // hoeft ook echt in de webshop te bestaan, denk aan een samenwerking
        // met een externe partij.
        $code = $discountCode->code ?? ($blockData['code'] ?? '');

        if ($code === '') {
            return '';
        }

        return view('dashed-ecommerce-core::emails.blocks.discount-code', [
            'code' => $code,
            'description' => $blockData['description'] ?? null,
            'validUntil' => $discountCode?->end_date?->format('d-m-Y'),
            'primaryColor' => $context['primaryColor'] ?? '#111827',
            'textColor' => $context['textColor'] ?? '#ffffff',
        ])->render();
    }
}
