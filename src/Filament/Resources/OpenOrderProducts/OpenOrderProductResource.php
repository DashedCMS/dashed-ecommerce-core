<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources\OpenOrderProducts;

use UnitEnum;
use BackedEnum;
use Filament\Tables\Table;
use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Builder;
use Dashed\DashedEcommerceCore\Models\OrderProduct;
use Dashed\DashedEcommerceCore\Filament\Resources\OpenOrderProducts\Pages\ListOpenOrderProducts;
use Dashed\DashedEcommerceCore\Filament\Resources\OpenOrderProducts\Tables\OpenOrderProductsTable;

class OpenOrderProductResource extends Resource
{
    protected static ?string $model = OrderProduct::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static string|UnitEnum|null $navigationGroup = 'E-commerce';

    protected static ?string $label = 'Openstaande bestelling';

    protected static ?string $pluralLabel = 'Openstaande bestellingen';

    public static function getNavigationLabel(): string
    {
        return 'Openstaande bestellingen';
    }

    public static function table(Table $table): Table
    {
        return OpenOrderProductsTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        // Standaard scope: alleen orderproducten van bestellingen die nog
        // niet afgehandeld zijn, en zonder shipping/payment cost regels.
        // SKU is gekwalificeerd zodat deze scope ook werkt wanneer een tab
        // de query joint met dashed__products (welke ook een sku-kolom heeft).
        return parent::getEloquentQuery()
            ->whereHas('order', fn ($q) => $q->where('fulfillment_status', 'unhandled'))
            ->where(function ($q) {
                $q->whereNull('dashed__order_products.sku')
                    ->orWhereNotIn('dashed__order_products.sku', ['shipping_costs', 'payment_costs']);
            })
            ->with(['order', 'product']);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOpenOrderProducts::route('/'),
        ];
    }
}
