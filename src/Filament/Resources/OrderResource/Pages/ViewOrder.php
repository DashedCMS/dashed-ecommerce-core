<?php

namespace Qubiqx\QcommerceEcommerceCore\Filament\Resources\OrderResource\Pages;

use Filament\Pages\Actions\ButtonAction;
use Filament\Resources\Pages\ViewRecord;
use Qubiqx\QcommerceEcommerceCore\Filament\Resources\OrderResource;

class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    protected static string $view = 'qcommerce-ecommerce-core::orders.view-order';

    protected $listeners = [
        'refreshPage' => 'render',
        'notify' => 'message'
    ];

    protected function getTitle(): string
    {
        return "Bestelling {$this->record->invoice_id} van {$this->record->name}";
    }

    protected function getActions(): array
    {
        return [
            ButtonAction::make('Bekijk in website')
                ->url($this->record->url)
                ->openUrlInNewTab(),
            ButtonAction::make('Download factuur')
                ->url($this->record->downloadInvoiceUrl())
                ->hidden(!$this->record->downloadInvoiceUrl()),
            ButtonAction::make('Download pakbon')
                ->url($this->record->downloadPackingslipUrl())
                ->hidden(!$this->record->downloadPackingslipUrl()),
        ];
    }

    public function message($notify)
    {
        $this->notify($notify['status'], $notify['message']);
    }
}
