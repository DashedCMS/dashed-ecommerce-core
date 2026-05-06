<?php

namespace Dashed\DashedEcommerceCore\Filament\Widgets\Product;

use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Models\OrderProduct;

/**
 * Footer-widget op de bewerk-pagina van een product. Toont de orderregels
 * van bestellingen die nog niet zijn afgehandeld waarin dit product voorkomt.
 */
class ProductOpenOrdersWidget extends TableWidget
{
    protected int|string|array $columnSpan = 'full';

    public ?Product $record = null;

    public function getHeading(): ?string
    {
        if (! $this->record) {
            return 'Openstaande bestellingen';
        }

        $count = $this->openOrdersBaseQuery()->count();
        $quantity = (int) $this->openOrdersBaseQuery()->sum('quantity');

        if ($count === 0) {
            return 'Openstaande bestellingen';
        }

        return "Openstaande bestellingen ({$count} regels, {$quantity} stuks)";
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->openOrdersBaseQuery()->with(['order']))
            ->paginated([5, 10, 25])
            ->defaultPaginationPageOption(5)
            ->emptyStateHeading('Geen openstaande bestellingen')
            ->emptyStateDescription('Er zijn op dit moment geen onafgehandelde bestellingen waarin dit product voorkomt.')
            ->columns([
                TextColumn::make('order.invoice_id')
                    ->label('Bestelling')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('order.name')
                    ->label('Klant'),
                TextColumn::make('quantity')
                    ->label('Aantal')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('order.fulfillment_status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state ? ucfirst(str_replace('_', ' ', $state)) : '-')
                    ->color(fn (?string $state): string => match ($state) {
                        'handled' => 'success',
                        'unhandled' => 'warning',
                        'in_treatment', 'packed', 'ready_for_pickup' => 'info',
                        'shipped' => 'primary',
                        default => 'gray',
                    }),
                TextColumn::make('order.created_at')
                    ->label('Besteld op')
                    ->dateTime('d-m-Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('order_id', 'desc');
    }

    protected function openOrdersBaseQuery(): Builder
    {
        $productId = $this->record?->id;

        $query = OrderProduct::query()
            ->whereHas('order', fn ($q) => $q->where('fulfillment_status', 'unhandled'));

        if ($productId) {
            $query->where('product_id', $productId);
        } else {
            $query->whereRaw('1 = 0');
        }

        return $query;
    }
}
