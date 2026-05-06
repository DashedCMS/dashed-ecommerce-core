<?php

namespace Dashed\DashedEcommerceCore\Filament\Widgets\Statistics;

use Carbon\Carbon;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Classes\CurrencyHelper;

/**
 * Toont de top-bronnen en top-campagnes van de afgelopen 30 dagen.
 * De widget combineert utm_source en utm_campaign in 1 tabel met een
 * kolom "Type" zodat we niet 2 widgets nodig hebben.
 */
class OrderAttributionStatsWidget extends TableWidget
{
    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Herkomst-statistieken (30 dagen)';

    public function table(Table $table): Table
    {
        return $table
            ->query($this->buildQuery())
            ->paginated(false)
            ->columns([
                TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->colors([
                        'primary' => 'Bron',
                        'success' => 'Campagne',
                    ]),
                TextColumn::make('value')
                    ->label('Waarde')
                    ->wrap(),
                TextColumn::make('order_count')
                    ->label('Aantal bestellingen')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('revenue')
                    ->label('Omzet')
                    ->formatStateUsing(fn ($state) => CurrencyHelper::formatPrice((float) $state)),
            ]);
    }

    protected function buildQuery(): Builder
    {
        // Als de attributie-kolommen nog niet bestaan, een lege query teruggeven.
        if (! Schema::hasColumn('dashed__orders', 'utm_source')) {
            return Order::query()->whereRaw('1 = 0');
        }

        $start = Carbon::now()->subDays(30);

        $sourceRows = DB::table('dashed__orders')
            ->selectRaw("'Bron' as type, utm_source as value, COUNT(*) as order_count, COALESCE(SUM(total), 0) as revenue")
            ->whereNull('deleted_at')
            ->where('created_at', '>=', $start)
            ->whereNotNull('utm_source')
            ->where('utm_source', '!=', '')
            ->groupBy('utm_source')
            ->orderByDesc('order_count')
            ->limit(5);

        $campaignRows = DB::table('dashed__orders')
            ->selectRaw("'Campagne' as type, utm_campaign as value, COUNT(*) as order_count, COALESCE(SUM(total), 0) as revenue")
            ->whereNull('deleted_at')
            ->where('created_at', '>=', $start)
            ->whereNotNull('utm_campaign')
            ->where('utm_campaign', '!=', '')
            ->groupBy('utm_campaign')
            ->orderByDesc('order_count')
            ->limit(5);

        $unionSql = $sourceRows->unionAll($campaignRows);

        // Een Eloquent-builder maken die paginatie ondersteunt door de
        // sub-query als FROM te gebruiken op een dummy-model (Order).
        return Order::query()
            ->fromSub($unionSql, 'attribution_stats')
            ->select(['type', 'value', 'order_count', 'revenue'])
            ->orderByDesc('order_count');
    }
}
