<?php

namespace Qubiqx\QcommerceEcommerceCore\Filament\Resources\OrderResource\Pages;

use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;
use Filament\Tables\Filters\Filter;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Section;
use Filament\Tables\Actions\BulkAction;
use Illuminate\Support\Facades\Storage;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Filament\Forms\Components\Placeholder;
use Qubiqx\QcommerceEcommerceCore\Models\Order;
use Qubiqx\QcommerceEcommerceCore\Classes\Orders;
use Qubiqx\QcommerceEcommerceCore\Models\OrderProduct;
use Qubiqx\QcommerceEcommerceCore\Filament\Resources\OrderResource;

class ListOrders extends ListRecords
{
    protected static string $resource = OrderResource::class;
    protected ?string $maxContentWidth = 'full';

    protected function getFilteredTableQuery(): Builder
    {
        $query = parent::getTableQuery();
        $this->applyColumnSearchToTableQuery($query);
        $this->applyGlobalSearchToTableQuery($query);
        $orderIds = $query->pluck('id')->toArray();
        $orderProductOrderIds = OrderProduct::search($this->getTableSearchQuery())->pluck('order_id')->toArray();

        $query = Order::whereIn('id', array_merge($orderIds, $orderProductOrderIds));

        $query = $this->applyFiltersToTableQuery($query);
        $query = $this->applySearchToTableQuery($query);

        foreach ($this->getCachedTableColumns() as $column) {
            $query = $column->applyEagerLoading($query);
            $query = $column->applyRelationshipAggregates($query);
        }

        return $query;
    }

    protected function applySearchToTableQuery(Builder $query): Builder
    {
        $originalQuery = clone $query;
        $orderIds = OrderProduct::search($this->getTableSearchQuery())->pluck('order_id')->toArray();

        $this->applyColumnSearchToTableQuery($query);
        $this->applyGlobalSearchToTableQuery($query);
        $orderIds = array_merge($orderIds, $query->pluck('id')->toArray());

        return $originalQuery->whereIn('id', $orderIds);
    }

    protected function getTableBulkActions(): array
    {
        return [
            BulkAction::make('downloadInvoices')
                ->label('Download facturen')
                ->color('primary')
                ->action('downloadInvoices')
                ->deselectRecordsAfterCompletion(),
            BulkAction::make('downloadPackingSlips')
                ->label('Download pakbonnen')
                ->color('primary')
                ->action('downloadPackingSlips')
                ->deselectRecordsAfterCompletion(),
            BulkAction::make('changeFulfillmentStatus')
                ->color('primary')
                ->label('Fulfillment status')
                ->form([
                    Select::make('fulfillment_status')
                        ->label('Veranderd fulfillment status naar')
                        ->options(Orders::getFulfillmentStatusses())
                        ->required(),
                ])
                ->action(function (Collection $records, array $data): void {
                    foreach ($records as $record) {
                        $record->changeFulfillmentStatus($data['fulfillment_status']);
                    }
                })
                ->deselectRecordsAfterCompletion(),
        ];
    }

    public function downloadInvoices(Collection $records)
    {
        $pdfMerger = \LynX39\LaraPdfMerger\Facades\PdfMerger::init();

        $hasPdf = false;
        foreach ($records as $order) {
            $url = $order->downloadInvoiceUrl();
            if ($url) {
                $invoicePath = storage_path('app/public/qcommerce/invoices/invoice-' . $order->invoice_id . '-' . $order->hash . '.pdf');
                $pdfMerger->addPDF($invoicePath, 'all');
                $hasPdf = true;
            }
        }

        if ($hasPdf) {
            $pdfMerger->merge();

            $invoicePath = '/qcommerce/exports/invoices/exported-invoice.pdf';
            Storage::put($invoicePath, '');
            $pdfMerger->save(storage_path('app/public' . $invoicePath));
            $this->notify('success', 'De export is gedownload');

            return Storage::download($invoicePath);
        } else {
            $this->notify('error', 'Geen facturen om te downloaden');
        }
    }

    public function downloadPackingSlips(Collection $records)
    {
        $pdfMerger = \LynX39\LaraPdfMerger\Facades\PdfMerger::init();

        $hasPdf = false;
        foreach ($records as $order) {
            $url = $order->downloadPackingSlipUrl();
            if ($url) {
                $packingSlipPath = storage_path('app/public/qcommerce/packing-slips/packing-slip-' . ($order->invoice_id ?: $order->id) . '-' . $order->hash . '.pdf');
                if (file_exists($packingSlipPath)) {
                    $pdfMerger->addPdf($packingSlipPath, 'all');
                    $hasPdf = true;
                }
            }
        }

        if ($hasPdf) {
            $pdfMerger->merge();

            $invoicePath = '/qcommerce/exports/packing-slips/exported-packing-slip.pdf';
            Storage::put($invoicePath, '');
            $pdfMerger->save(storage_path('app/public' . $invoicePath));
            $this->notify('success', 'De export is gedownload');

            return Storage::download($invoicePath);
        } else {
            $this->notify('error', 'Geen pakbonnen om te downloaden');
        }
    }

    protected function getActions(): array
    {
        return array_merge(parent::getActions(), ecommerce()->buttonActions('orders'));
    }

    protected function getTableActions(): array
    {
        //        $quickOrderProducts = [];

        //        foreach(fn($record) => $record->orderProducts as $orderProduct){
        //            dd($orderProduct);
        //        }

        return array_merge(parent::getTableActions(), [
            \Filament\Tables\Actions\Action::make('quickActions')
                ->button()
                ->label('Quick')
                ->color('primary')
                ->modalHeading('Snel bewerken')
                ->modalButton('Opslaan')
                ->modalFooter(fn ($record) => view('qcommerce-ecommerce-core::orders.quick-view-order', ['record' => $record]))
                ->form([
//                    Section::make('Status')
//                        ->schema([
//                            Select::make('fulfillment_status')
//                                ->label('Fulfillment status')
//                                ->options(Orders::getFulfillmentStatusses())
//                                ->required()
//                                ->default(fn($record) => $record->fulfillment_status)
//                                ->hidden(fn($record) => $record->credit_for_order_id),
//                            Select::make('retour_status')
//                                ->label('Retour status')
//                                ->options(Orders::getReturnStatusses())
//                                ->required()
//                                ->default(fn($record) => $record->retour_status)
//                                ->hidden(fn($record) => !$record->credit_for_order_id),
//                        ])
//                        ->columns([
//                            'default' => 1,
//                            'lg' => 2,
//                        ]),
                    Section::make('Informatie')
                        ->schema([
                            Placeholder::make('shippingAddress')
                                ->label('Verzendadres')
                                ->content(fn ($record) => new HtmlString(($record->company_name ? $record->company_name . ' < br>' : '') . "$record->name<br>$record->street $record->house_nr<br>$record->city $record->zip_code<br>$record->country")),
                            Placeholder::make('shippingAddress')
                                ->label('Factuuradres')
                                ->content(fn ($record) => new HtmlString(($record->company_name ? $record->company_name . ' < br>' : '') . "$record->name<br>$record->invoice_street $record->invoice_house_nr<br>$record->invoice_city $record->invoice_zip_code<br>$record->invoice_country")),
                        ])
                        ->columns([
                            'default' => 1,
                            'lg' => 2,
                        ]),
                ])
                ->action(function (Order $record, array $data): void {
                    if (isset($data['fulfillment_status'])) {
                        $record->fulfillment_status = $data['fulfillment_status'];
                    }
                    if (isset($data['retour_status'])) {
                        $record->retour_status = $data['retour_status'];
                    }
                    $record->save();
                }),
        ]);
    }

    protected function getTableFilters(): array
    {
        $orderOrigins = [];
        foreach (Order::distinct('order_origin')->pluck('order_origin')->unique() as $orderOrigin) {
            $orderOrigins[$orderOrigin] = ucfirst($orderOrigin);
        }

        return [
            SelectFilter::make('status')
                ->multiple()
                ->form([
                    Select::make('values')
                        ->label('Status')
                        ->multiple()
                        ->options([
                            'paid' => 'Betaald',
                            'partially_paid' => 'Gedeeltelijk betaald',
                            'waiting_for_confirmation' => 'Wachten op bevestiging',
                            'pending' => 'Lopende aankoop',
                            'cancelled' => 'Geannuleerd',
                            'return ' => 'Retour',
                        ])
                        ->default(['paid', 'partially_paid', 'waiting_for_confirmation']),
                ]),
//            MultiSelectFilter::make('status')
//                ->options([
//                    'paid' => 'Betaald',
//                    'partially_paid' => 'Gedeeltelijk betaald',
//                    'waiting_for_confirmation' => 'Wachten op bevestiging',
//                    'pending' => 'Lopende aankoop',
//                    'cancelled' => 'Geannuleerd',
//                    'return ' => 'Retour',
//                ]),
//            MultiSelectFilter::make('payment_method')
//                ->options(OrderPayment::whereNotNull('payment_method')->distinct('payment_method')->pluck('payment_method')->unique()),
            SelectFilter::make('fulfillment_status')
                ->multiple()
                ->options(Orders::getFulfillmentStatusses()),
            SelectFilter::make('retour_status')
                ->multiple()
                ->options(Orders::getReturnStatusses()),
            SelectFilter::make('order_origin')
                ->multiple()
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
                            fn (Builder $query, $date): Builder => $query->whereDate('created_at', ' >= ', $date),
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
                            fn (Builder $query, $date): Builder => $query->whereDate('created_at', ' <= ', $date),
                        );
                }),
        ];
    }

    protected function getTableFiltersFormColumns(): int|array
    {
        return 4;
    }

    protected function shouldPersistTableFiltersInSession(): bool
    {
        return true;
    }
}
