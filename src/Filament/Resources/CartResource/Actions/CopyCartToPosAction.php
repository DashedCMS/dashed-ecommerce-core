<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources\CartResource\Actions;

use Filament\Actions\Action;
use Illuminate\Support\Facades\Auth;
use Filament\Forms\Components\Checkbox;
use Filament\Notifications\Notification;
use Dashed\DashedEcommerceCore\Models\Cart;
use Dashed\DashedEcommerceCore\Classes\CartToPosService;
use Dashed\DashedEcommerceCore\Filament\Pages\POS\POSPageRedirect;

class CopyCartToPosAction
{
    public static function make(): Action
    {
        $kassaVol = fn (): bool => CartToPosService::activePosCartHasProducts((int) Auth::id());

        return Action::make('copyCartToPos')
            ->label(__('Naar kassa'))
            ->icon('heroicon-o-computer-desktop')
            ->visible(fn (Cart $record): bool => POSPageRedirect::canAccess()
                && ! in_array($record->type, ['pos', 'customer-pos'], true)
                && $record->items()->exists())
            ->modalHeading(fn (): string => $kassaVol() ? __('Kassa-winkelwagen vervangen?') : __('Winkelwagen naar kassa'))
            ->modalDescription(fn (): string => $kassaVol()
                ? __('Je kassa bevat al producten. Die worden vervangen door de inhoud van deze winkelwagen.')
                : __('De producten uit deze winkelwagen worden in je kassa gezet. De winkelwagen van de klant blijft bestaan.'))
            ->modalSubmitActionLabel(__('Naar kassa'))
            ->form(fn (Cart $record): array => [
                Checkbox::make('copy_customer_details')
                    ->label(__('Klantgegevens meenemen'))
                    ->helperText(__('Het klantaccount en het e-mailadres van deze winkelwagen gaan mee naar de kassa'))
                    ->default(true)
                    ->visible(filled($record->user_id) || filled($record->abandoned_email)),
                Checkbox::make('copy_discount_code')
                    ->label(fn (): string => __('Kortingscode :code meenemen', ['code' => (string) $record->discountCode?->code]))
                    ->default(true)
                    ->visible((bool) $record->discountCode && ! $record->discountCode->is_giftcard),
            ])
            ->action(function (Cart $record, array $data, $livewire) {
                abort_unless(POSPageRedirect::canAccess(), 403);

                if (! $record->items()->exists()) {
                    Notification::make()->title(__('Deze winkelwagen is leeg'))->warning()->send();

                    return;
                }

                CartToPosService::copyIntoCart(
                    CartToPosService::activePosCart((int) Auth::id()),
                    $record,
                    copyCustomerDetails: (bool) ($data['copy_customer_details'] ?? false),
                    copyDiscountCode: (bool) ($data['copy_discount_code'] ?? false),
                );

                $livewire->redirect(route('dashed.ecommerce.point-of-sale'));
            });
    }
}
