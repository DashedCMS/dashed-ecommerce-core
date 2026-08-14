<?php

namespace Dashed\DashedEcommerceCore\Mail\EmailBlocks;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Builder\Block;
use Dashed\DashedEcommerceCore\Models\DiscountCode;
use Dashed\DashedCore\Mail\EmailBlocks\EmailBlock;

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
                    ->options(fn (): array => DiscountCode::pluck('code', 'id')->all())
                    ->searchable(),
                TextInput::make('code')
                    ->label(__('Code (als deze niet in de webshop staat)')),
                TextInput::make('description')
                    ->label(__('Omschrijving')),
            ]);
    }

    public static function render(array $blockData, array $context): string
    {
        $discountCode = ! empty($blockData['discount_code_id'])
            ? DiscountCode::find($blockData['discount_code_id'])
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
