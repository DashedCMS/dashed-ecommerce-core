<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources\OrderResource\Pages;

use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\Printer;
use Dashed\DashedEcommerceCore\Models\PrintJob;
use Dashed\DashedEcommerceCore\Enums\PrinterType;
use Dashed\DashedEcommerceCore\Enums\PrintJobType;
use Dashed\DashedEcommerceCore\Enums\PrintJobStatus;
use Dashed\DashedEcommerceCore\Filament\Resources\OrderResource;
use Dashed\DashedEcommerceCore\Filament\Resources\OrderResource\Actions\SendPaymentLinkAction;
use Dashed\DashedEcommerceCore\Filament\Resources\OrderResource\Actions\RegenerateInvoiceAction;
use Dashed\DashedEcommerceCore\Filament\Resources\OrderResource\Actions\RegisterManualPaymentAction;

class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    protected string $view = 'dashed-ecommerce-core::orders.view-order';

    protected $listeners = [
        'refreshPage' => 'renderPage',
        'notify' => 'message',
    ];

    public function getTitle(): string
    {
        return "Bestelling {$this->record->invoice_id} van {$this->record->name}";
    }

    /** Is er een actieve printer die verzendlabels kan printen (wachtrij)? */
    private function labelPrinterAvailable(): bool
    {
        return Printer::active()
            ->whereIn('type', [PrinterType::ShippingLabel->value, PrinterType::Both->value])
            ->exists();
    }

    /** Bestaat er een verzendlabel voor deze order (MyParcel-shipment of Veloyd-PDF)? */
    private function orderHasLabel(): bool
    {
        $orderId = $this->record->id;

        $myParcel = class_exists(\Dashed\DashedEcommerceMyParcel\Models\MyParcelOrder::class)
            && \Dashed\DashedEcommerceMyParcel\Models\MyParcelOrder::where('order_id', $orderId)
                ->whereNotNull('shipment_id')
                ->exists();

        $veloyd = class_exists(\Dashed\DashedEcommerceVeloyd\Models\VeloydOrder::class)
            && \Dashed\DashedEcommerceVeloyd\Models\VeloydOrder::where('order_id', $orderId)
                ->whereNotNull('label_pdf_path')
                ->exists();

        return $myParcel || $veloyd;
    }

    protected function getActions(): array
    {
        $invoiceUrl = $this->record->downloadInvoiceUrl();
        $packingSlipUrl = $this->record->downloadPackingslipUrl();

        $previousOrder = $this->record->fulfillment_status == 'unhandled' ? Order::where('id', '<', $this->record->id)
            ->orderBy('id', 'desc')
            ->where('fulfillment_status', 'unhandled')
            ->isPaid()
            ->first() : '';
        $nextOrder = $this->record->fulfillment_status == 'unhandled' ? Order::where('id', '>', $this->record->id)
            ->orderBy('id', 'asc')
            ->where('fulfillment_status', 'unhandled')
            ->isPaid()
            ->first() : '';

        return array_merge([
            Action::make('Vorige bestelling')
                ->hiddenLabel()
                ->icon('heroicon-s-arrow-left')
                ->url(fn () => $previousOrder ? route('filament.dashed.resources.orders.view', ['record' => $previousOrder->id]) : '')
                ->visible((bool)$previousOrder)
                ->tooltip('Bekijk de vorige onverwerkte bestelling'),
            Action::make('Volgende bestelling')
                ->hiddenLabel()
                ->icon('heroicon-s-arrow-right')
                ->url(fn () => $nextOrder ? route('filament.dashed.resources.orders.view', ['record' => $nextOrder->id]) : '')
                ->visible((bool)$nextOrder)
                ->tooltip('Bekijk de volgende onverwerkte bestelling'),
            Action::make('viewInWebsite')
                ->hiddenLabel()
                ->icon('heroicon-s-globe-alt')
                ->url($this->record->getUrl())
                ->tooltip('Bekijk bestelling in de webshop')
                ->openUrlInNewTab(),
            Action::make('edit')
                ->hiddenLabel()
                ->icon('heroicon-s-pencil-square')
                ->tooltip('Bewerk bestelling')
                ->url(route('filament.dashed.resources.orders.edit', ['record' => $this->record])),
            ActionGroup::make([
                Action::make('Factuur')
                    ->tooltip('Download de factuur als PDF')
                    ->icon('heroicon-s-arrow-down-tray')
                    ->url($invoiceUrl)
                    ->openUrlInNewTab()
                    ->visible((bool)$invoiceUrl),
                Action::make('Pakbon')
                    ->icon('heroicon-s-arrow-down-tray')
                    ->tooltip('Download de pakbon als PDF')
                    ->url($packingSlipUrl)
                    ->openUrlInNewTab()
                    ->visible((bool)$packingSlipUrl),
                RegenerateInvoiceAction::make($this->record),
                Action::make('printLabel')
                    ->label('Label printen')
                    ->icon('heroicon-s-printer')
                    ->tooltip('Stuur het verzendlabel naar de label-printer (wachtrij)')
                    ->visible(fn (): bool => $this->labelPrinterAvailable() && $this->orderHasLabel())
                    ->requiresConfirmation()
                    ->modalHeading('Label printen')
                    ->modalDescription('Het verzendlabel wordt naar de actieve label-printer gestuurd. De printer-daemon pakt het binnen enkele seconden op.')
                    ->action(function (): void {
                        PrintJob::create([
                            'type' => PrintJobType::ShippingLabel,
                            'order_id' => $this->record->id,
                            'status' => PrintJobStatus::Pending,
                        ]);

                        Notification::make()
                            ->title('Label naar de printer gestuurd')
                            ->success()
                            ->send();
                    }),
            ])
                ->label('Documenten')
                ->icon('heroicon-o-document-text')
                ->button(),
            ActionGroup::make([
                Action::make('markCancelledAsPaid')
                    ->label('Markeer als betaald')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn () => $this->record->status === 'cancelled')
                    ->requiresConfirmation()
                    ->modalHeading('Geannuleerde bestelling op betaald zetten?')
                    ->modalDescription('De bestelling wordt alsnog op betaald gezet (factuur, voorraad en afhandeling worden verwerkt).')
                    ->action(function () {
                        $this->record->changeStatus('paid');

                        Notification::make()
                            ->title('Bestelling op betaald gezet')
                            ->success()
                            ->send();

                        $this->redirect(route('filament.dashed.resources.orders.view', ['record' => $this->record->id]));
                    }),
                RegisterManualPaymentAction::make($this->record),
                SendPaymentLinkAction::make($this->record),
            ])
                ->label('Betaling')
                ->icon('heroicon-o-banknotes')
                ->color('primary')
                ->button(),
        ], ecommerce()->buttonActions('order'));
    }

    public function renderPage()
    {
        $this->redirect(route('filament.dashed.resources.orders.view', [$this->record]));
    }

    public function message($notify)
    {
        Notification::make()
            ->title($notify['message'])
            ->{$notify['status']}()
            ->send();
    }
}
