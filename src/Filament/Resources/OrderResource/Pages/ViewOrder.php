<?php

namespace Qubiqx\QcommerceEcommerceCore\Filament\Resources\OrderResource\Pages;

use Filament\Pages\Actions\Action;
use Filament\Pages\Actions\ButtonAction;
use Filament\Resources\Pages\ViewRecord;
use Qubiqx\QcommerceEcommerceCore\Filament\Resources\OrderResource;

class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    protected static string $view = 'qcommerce-ecommerce-core::orders.view-order';

    protected $listeners = [
        'refreshPage' => 'renderPage',
        'notify' => 'message',
    ];

    protected function getTitle(): string
    {
        return "Bestelling {$this->record->invoice_id} van {$this->record->name}";
    }

    protected function getActions(): array
    {
        return array_merge([
            Action::make('Bekijk in website')
                ->button()
                ->url($this->record->getUrl())
                ->openUrlInNewTab(),
            Action::make('Bewerk bestelling')
                ->button()
                ->url(route('filament.resources.orders.edit', ['record' => $this->record])),
            Action::make('Download factuur')
                ->button()
                ->url($this->record->downloadInvoiceUrl())
                ->hidden(!$this->record->downloadInvoiceUrl()),
            Action::make('Download pakbon')
                ->button()
                ->url($this->record->downloadPackingslipUrl())
                ->hidden(!$this->record->downloadPackingslipUrl()),
        ], ecommerce()->buttonActions('order'));
    }

    public function renderPage()
    {
        $this->redirect(route('filament.resources.orders.view', [$this->record]));
    }

    public function message($notify)
    {
        $this->notify($notify['status'], $notify['message']);
    }
}
