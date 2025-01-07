<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources\OrderResource\Pages;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Dashed\DashedEcommerceCore\Filament\Resources\OrderResource;

class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    protected static string $view = 'dashed-ecommerce-core::orders.view-order';

    protected $listeners = [
        'refreshPage' => 'renderPage',
        'notify' => 'message',
    ];

    public function getTitle(): string
    {
        return "Bestelling {$this->record->invoice_id} van {$this->record->name}";
    }

    protected function getActions(): array
    {
        $invoiceUrl = $this->record->downloadInvoiceUrl();
        $packingSlipUrl = $this->record->downloadPackingslipUrl();

        return array_merge([
            Action::make('viewInWebsite')
                ->hiddenLabel()
                ->icon('heroicon-s-globe-alt')
                ->button()
                ->url($this->record->getUrl())
                ->openUrlInNewTab(),
            Action::make('edit')
                ->hiddenLabel()
                ->icon('heroicon-s-pencil-square')
                ->button()
                ->url(route('filament.dashed.resources.orders.edit', ['record' => $this->record])),
            Action::make('Factuur')
                ->button()
                ->icon('heroicon-s-arrow-down-tray')
                ->url($invoiceUrl)
                ->visible((bool)$invoiceUrl),
            Action::make('Pakbon')
                ->button()
                ->icon('heroicon-s-arrow-down-tray')
                ->url($packingSlipUrl)
                ->visible((bool)$packingSlipUrl),
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
