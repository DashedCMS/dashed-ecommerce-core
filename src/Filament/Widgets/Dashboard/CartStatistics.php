<?php

namespace Dashed\DashedEcommerceCore\Filament\Widgets\Dashboard;

use Filament\Widgets\StatsOverviewWidget;
use Dashed\DashedEcommerceCore\Models\Cart;
use Dashed\DashedEcommerceCore\Models\CartItem;
use Dashed\DashedEcommerceCore\Classes\CurrencyHelper;

class CartStatistics extends StatsOverviewWidget
{
    protected static ?int $sort = 5;

    protected function getHeading(): ?string
    {
        return 'Winkelwagen statistieken';
    }

    protected function getCards(): array
    {
        $activeCarts = Cart::whereHas('items')->get();
        $cartItems = CartItem::whereIn('cart_id', $activeCarts->pluck('id'))->get();

        $cartItemsValue = 0;
        foreach ($cartItems as $cartItem) {
            $cartItemsValue += $cartItem->unit_price * $cartItem->quantity;
        }

        return [
            StatsOverviewWidget\Stat::make('Aantal actieve winkelwagens', $activeCarts->count()),
            StatsOverviewWidget\Stat::make('Aantal producten in winkelwagens', $cartItems->sum('quantity')),
            StatsOverviewWidget\Stat::make('Waarde in winkelwagens', CurrencyHelper::formatPrice($cartItemsValue)),
        ];
    }
}
