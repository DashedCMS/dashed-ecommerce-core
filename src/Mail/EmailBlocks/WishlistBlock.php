<?php

namespace Dashed\DashedEcommerceCore\Mail\EmailBlocks;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Builder\Block;
use Dashed\DashedEcommerceCore\Models\Wishlist;
use Dashed\DashedCore\Mail\EmailBlocks\EmailBlock;
use Dashed\DashedEcommerceCore\Models\WishlistItem;
use Dashed\DashedEcommerceCore\Controllers\Frontend\WishlistController;

/**
 * "Jouw verlanglijst" in een nieuwsbrief. Per ontvanger gerenderd (zie
 * EmailBlock::perRecipient()); zonder ontvanger levert het blok niets op,
 * zodat een preview zonder gekozen contact geen gat toont maar ook niets
 * verkeerds.
 */
class WishlistBlock extends EmailBlock
{
    public static function key(): string
    {
        return 'wishlist';
    }

    public static function label(): string
    {
        return __('Jouw verlanglijst');
    }

    public static function contexts(): array
    {
        return [self::CONTEXT_NEWSLETTER];
    }

    public static function perRecipient(): bool
    {
        return true;
    }

    public static function filamentBlock(): Block
    {
        return Block::make(self::key())
            ->label(self::label())
            ->icon('heroicon-o-heart')
            ->schema([
                TextInput::make('limit')->label(__('Aantal producten'))->numeric()->default(4)->minValue(1)->maxValue(12),
                Select::make('columns')->label(__('Aantal kolommen'))->options([1 => '1', 2 => '2', 3 => '3', 4 => '4'])->default(2),
                Select::make('empty')
                    ->label(__('Als de lijst leeg is'))
                    ->options(['verbergen' => __('Blok verbergen'), 'bestsellers' => __('Bestsellers tonen')])
                    ->default('verbergen')
                    ->helperText(__('Wordt per ontvanger bepaald op het moment van verzenden.')),
                TextInput::make('button_label')->label(__('Knoptekst'))->default('Bekijk je verlanglijst'),
            ]);
    }

    public static function render(array $blockData, array $context): string
    {
        return '';
    }

    public static function renderForRecipient(array $blockData, array $context): string
    {
        $email = (string) ($context['recipientEmail'] ?? '');
        $limit = max(1, min(12, (int) ($blockData['limit'] ?? 4)));
        $columns = (int) ($blockData['columns'] ?? 2);

        $wishlist = $email !== '' ? Wishlist::forEmail($email)->orderByDesc('last_activity_at')->first() : null;
        $items = $wishlist?->publicItems()->take($limit) ?? collect();

        if ($items->isEmpty()) {
            return ($blockData['empty'] ?? 'verbergen') === 'bestsellers'
                ? AutoProductsBlock::render(['selection' => 'best_sold', 'limit' => $limit, 'columns' => $columns], $context)
                : '';
        }

        $raster = ProductsBlock::renderProducts($items->map(fn (WishlistItem $item) => $item->product), $columns, $context);

        $gedaald = $items->filter(fn (WishlistItem $item) => $item->priceDropped());
        $melding = $gedaald->isNotEmpty()
            ? '<tr><td style="padding:0 24px 8px;font-family:Arial,Helvetica,sans-serif;font-size:13px;color:#166534;">' . __('Prijs gedaald') . ': ' . e($gedaald->map(fn ($i) => $i->product->name)->implode(', ')) . '</td></tr>'
            : '';

        $knop = '<tr><td style="padding:0 24px 24px;text-align:center;">'
            . '<a href="' . e(WishlistController::restoreUrl($wishlist)) . '" style="display:inline-block;padding:12px 24px;background:' . e($context['primaryColor'] ?? '#111827') . ';color:' . e($context['textColor'] ?? '#ffffff') . ';text-decoration:none;border-radius:6px;font-weight:bold;font-family:Arial,Helvetica,sans-serif;">'
            . e((string) (($blockData['button_label'] ?? '') ?: __('Bekijk je verlanglijst'))) . '</a></td></tr>';

        return $melding . $raster . $knop;
    }
}
