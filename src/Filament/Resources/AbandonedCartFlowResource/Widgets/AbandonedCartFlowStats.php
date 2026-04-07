<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources\AbandonedCartFlowResource\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Dashed\DashedEcommerceCore\Models\AbandonedCartEmail;
use Dashed\DashedEcommerceCore\Models\AbandonedCartClick;
use Illuminate\Database\Eloquent\Model;

class AbandonedCartFlowStats extends StatsOverviewWidget
{
    public ?Model $record = null;

    protected function getStats(): array
    {
        if (! $this->record) {
            return [];
        }

        $stepIds = $this->record->steps()->pluck('id');

        $sent = AbandonedCartEmail::whereIn('flow_step_id', $stepIds)->whereNotNull('sent_at')->count();
        $clicked = AbandonedCartEmail::whereIn('flow_step_id', $stepIds)->whereNotNull('clicked_at')->count();
        $converted = AbandonedCartEmail::whereIn('flow_step_id', $stepIds)->whereNotNull('converted_at')->count();
        $totalClicks = AbandonedCartClick::whereIn('abandoned_cart_email_id',
            AbandonedCartEmail::whereIn('flow_step_id', $stepIds)->pluck('id')
        )->count();
        $productClicks = AbandonedCartClick::whereIn('abandoned_cart_email_id',
            AbandonedCartEmail::whereIn('flow_step_id', $stepIds)->pluck('id')
        )->where('link_type', 'product')->count();
        $buttonClicks = AbandonedCartClick::whereIn('abandoned_cart_email_id',
            AbandonedCartEmail::whereIn('flow_step_id', $stepIds)->pluck('id')
        )->where('link_type', 'button')->count();

        $clickRate = $sent > 0 ? round(($clicked / $sent) * 100, 1) : 0;
        $conversionRate = $clicked > 0 ? round(($converted / $clicked) * 100, 1) : 0;

        $revenue = AbandonedCartEmail::whereIn('flow_step_id', $stepIds)
            ->whereNotNull('converted_at')
            ->whereNotNull('order_id')
            ->with('order')
            ->get()
            ->sum(fn ($email) => $email->order?->total ?? 0);

        return [
            Stat::make('Verzonden', $sent)
                ->icon('heroicon-o-paper-airplane'),
            Stat::make('Geklikt', $clicked)
                ->description($clickRate . '% klikratio')
                ->icon('heroicon-o-cursor-arrow-rays'),
            Stat::make('Conversies', $converted)
                ->description($conversionRate . '% conversieratio')
                ->icon('heroicon-o-shopping-cart')
                ->color('success'),
            Stat::make('Omzet', '€ ' . number_format($revenue, 2, ',', '.'))
                ->icon('heroicon-o-banknotes')
                ->color('success'),
            Stat::make('Knop kliks', $buttonClicks)
                ->icon('heroicon-o-hand-raised'),
            Stat::make('Product kliks', $productClicks)
                ->icon('heroicon-o-shopping-bag'),
        ];
    }
}
