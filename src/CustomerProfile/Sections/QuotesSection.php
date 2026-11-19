<?php

namespace Dashed\DashedEcommerceCore\CustomerProfile\Sections;

use Illuminate\Database\Eloquent\Builder;
use Dashed\DashedEcommerceCore\Models\Quote;
use Dashed\DashedEcommerceCore\Classes\CurrencyHelper;
use Dashed\DashedEcommerceCore\Filament\Resources\QuoteResource;

class QuotesSection
{
    public static function label(): string
    {
        return __('Offertes');
    }

    public static function sort(): int
    {
        return 30;
    }

    public static function forCustomer(?int $userId, ?string $email): ?array
    {
        $quotes = Quote::query()
            ->where(function (Builder $query) use ($userId, $email) {
                $query->whereRaw('1 = 0');
                if ($userId) {
                    $query->orWhere('user_id', $userId);
                }
                if ($email) {
                    $query->orWhere('email', $email);
                }
            })
            ->latest('id')
            ->limit(25)
            ->get();

        if ($quotes->isEmpty()) {
            return null;
        }

        return [
            'columns' => [
                'number' => __('Offerte'),
                'date' => __('Datum'),
                'total' => __('Totaal'),
                'status' => __('Status'),
            ],
            'rows' => $quotes->map(fn ($quote) => [
                'number' => (string) $quote->displayNumber(),
                'date' => $quote->created_at?->format('d-m-Y'),
                'total' => CurrencyHelper::formatPrice($quote->total),
                'status' => (string) $quote->status,
                '_url' => rescue(fn () => QuoteResource::getUrl('edit', ['record' => $quote->id]), null, false),
            ])->all(),
            'empty' => __('Geen offertes.'),
        ];
    }
}
