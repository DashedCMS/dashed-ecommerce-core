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
        return array_merge([
            Action::make('Bekijk in website')
                ->button()
                ->url($this->record->getUrl())
                ->openUrlInNewTab(),
            Action::make('Bewerk bestelling')
                ->button()
                ->url(route('filament.dashed.resources.orders.edit', ['record' => $this->record])),
            Action::make('Download factuur')
                ->button()
                ->url($this->record->downloadInvoiceUrl())
                ->visible((bool)$this->record->downloadInvoiceUrl()),
            Action::make('Download pakbon')
                ->button()
                ->url($this->record->downloadPackingslipUrl())
                ->visible((bool)$this->record->downloadPackingslipUrl()),
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
