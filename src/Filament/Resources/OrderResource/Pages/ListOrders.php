<?php

namespace Qubiqx\QcommerceEcommerceCore\Filament\Resources\OrderResource\Pages;

use Filament\Forms\Components\DatePicker;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\MultiSelectFilter;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Storage;
use Qubiqx\QcommerceEcommerceCore\Classes\Orders;
use Qubiqx\QcommerceEcommerceCore\Filament\Resources\OrderResource;
use Qubiqx\QcommerceEcommerceCore\Models\Order;
use Qubiqx\QcommerceEcommerceCore\Models\OrderPayment;

class ListOrders extends ListRecords
{
    protected static string $resource = OrderResource::class;

    protected function getTableBulkActions(): array
    {
        return [
            BulkAction::make('downloadInvoices')
                ->label('Download facturen')
                ->color('primary')
                ->action(fn(Collection $records) => function ($records) {
                    return Storage::download('/exports/invoices/exported-invoice.pdf');
                })
        ];
    }

    protected function getTableFilters(): array
    {
        $orderOrigins = [];
        foreach(Order::distinct('order_origin')->pluck('order_origin')->unique() as $orderOrigin){
            $orderOrigins[$orderOrigin] = ucfirst($orderOrigin);
        }

        return [
            MultiSelectFilter::make('status')
                ->options([
                    'paid' => 'Betaald',
                    'partially_paid' => 'Gedeeltelijk betaald',
                    'waiting_for_confirmation' => 'Wachten op bevestiging',
                    'pending' => 'Lopende aankoop',
                    'cancelled' => 'Geannuleerd',
                    'return' => 'Retour',
                ]),
//            MultiSelectFilter::make('payment_method')
//                ->options(OrderPayment::whereNotNull('payment_method')->distinct('payment_method')->pluck('payment_method')->unique()),
            MultiSelectFilter::make('fulfillment_status')
                ->options(Orders::getFulfillmentStatusses()),
            MultiSelectFilter::make('retour_status')
                ->options([
                    'handled' => 'Afgehandeld',
                    'unhandled' => 'Niet afgehandeld',
                    'received' => 'Ontvangen',
                    'shipped' => 'Onderweg',
                    'waiting_for_return' => 'Wachten op retour',
                ]),
            MultiSelectFilter::make('order_origin')
                ->options($orderOrigins),
            Filter::make('start_date')
                ->form([
                    DatePicker::make('start_date')
                        ->label('Startdatum'),
                ])
                ->query(function (Builder $query, array $data): Builder {
                    return $query
                        ->when(
                            $data['start_date'],
                            fn(Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                        );
                }),
            Filter::make('end_date')
                ->form([
                    DatePicker::make('end_date')
                        ->label('Einddatum'),
                ])
                ->query(function (Builder $query, array $data): Builder {
                    return $query
                        ->when(
                            $data['end_date'],
                            fn(Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                        );
                })
        ];
    }

    protected function getTableFiltersFormColumns(): int|array
    {
        return 4;
    }
}
