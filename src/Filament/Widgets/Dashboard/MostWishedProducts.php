<?php

namespace Dashed\DashedEcommerceCore\Filament\Widgets\Dashboard;

use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Facades\DB;
use Filament\Tables\Columns\TextColumn;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Filament\Resources\ProductResource;

class MostWishedProducts extends TableWidget
{
    protected static ?string $heading = 'Meest gewenst (90 dagen)';

    protected int|string|array $columnSpan = 'full';

    /**
     * `name` is een translatable kolom (Spatie, JSON per locale), dus de ruwe
     * kolom levert `{"nl":"…"}` op; JSON_EXTRACT haalt de huidige taal eruit
     * met terugval op 'nl' en dan de ruwe waarde (zelfde patroon als
     * ListOpenOrderProducts). De locale wordt gewhitelist op letters om
     * SQL-injectie via een gemanipuleerde app-locale uit te sluiten.
     *
     * @return Collection<int, object{product_id:int,name:string,aantal:int}>
     */
    public static function top(int $days = 90, int $limit = 10): Collection
    {
        $locale = preg_replace('/[^a-zA-Z]/', '', (string) app()->getLocale()) ?: 'nl';

        return DB::table('dashed__wishlist_items')
            ->join('dashed__products', 'dashed__products.id', '=', 'dashed__wishlist_items.product_id')
            ->where('dashed__wishlist_items.updated_at', '>=', now()->subDays($days))
            ->groupBy('dashed__wishlist_items.product_id', 'dashed__products.name')
            ->orderByDesc('aantal')
            ->limit($limit)
            ->get([
                'dashed__wishlist_items.product_id',
                DB::raw(
                    "COALESCE(
                        NULLIF(JSON_UNQUOTE(JSON_EXTRACT(dashed__products.name, '$.\"".$locale."\"')), 'null'),
                        NULLIF(JSON_UNQUOTE(JSON_EXTRACT(dashed__products.name, '$.\"nl\"')), 'null'),
                        dashed__products.name
                    ) as name"
                ),
                DB::raw('count(*) as aantal'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => Product::query()
                ->whereIn('id', self::top()->pluck('product_id'))
                ->withCount(['wishlistItems as aantal' => fn ($q) => $q->where('updated_at', '>=', now()->subDays(90))])
                ->orderByDesc('aantal'))
            ->columns([
                TextColumn::make('name')->label('Product')->url(fn (Product $p) => ProductResource::getUrl('edit', ['record' => $p])),
                TextColumn::make('aantal')->label('Op verlanglijsten'),
            ])
            ->paginated(false);
    }
}
