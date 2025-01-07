<?php

namespace Dashed\DashedEcommerceCore\Livewire\Orders\Infolists;

use Livewire\Component;
use Filament\Infolists\Infolist;
use Filament\Forms\Contracts\HasForms;
use Filament\Infolists\Components\Fieldset;
use Dashed\DashedEcommerceCore\Models\Order;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Contracts\HasInfolists;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Concerns\InteractsWithInfolists;

class PaymentInformationList extends Component implements HasForms, HasInfolists
{
    use InteractsWithForms;
    use InteractsWithInfolists;

    public Order $order;

    protected $listeners = [
        'refreshData' => '$refresh',
    ];

    public function mount($order)
    {
        $this->order = $order;
    }

    public function infolist(Infolist $infolist): Infolist
    {
        $customOrderFields = [];

        foreach ($this->order->customOrderFields() as $label => $value) {
            $customOrderFields[] = TextEntry::make(str($label)->slug())
                ->label($label)
                ->getStateUsing(fn($record) => $value);
        }

        return $infolist
            ->record($this->order)
            ->schema([
                Fieldset::make('Betaal informatie')
                    ->schema([
                        TextEntry::make('order_origin')
                            ->label('Bestellingsherkomst'),
                        TextEntry::make('ip')
                            ->label('IP'),
                        TextEntry::make('note')
                            ->label('Notitie')
                            ->getStateUsing(fn($record) => $record->note ?: 'Geen notitie'),
                        IconEntry::make('marketing')
                            ->label('Marketing geaccepteerd')
                            ->trueIcon('heroicon-o-check-circle')
                            ->falseIcon('heroicon-o-x-circle'),
                        TextEntry::make('invoice_id')
                            ->label('Factuur ID'),
                        TextEntry::make('paymentMethod')
                            ->label('Betalingsmethode')
                            ->getStateUsing(fn($record) => $record->paymentMethod ?? 'Niet gevonden'),
                        TextEntry::make('psp')
                            ->label('PSP')
                            ->visible(fn($record) => $record->psp),
                        TextEntry::make('psp_id')
                            ->label('PSP ID')
                            ->visible(fn($record) => $record->psp),
                        TextEntry::make('order_origin')
                            ->label('Verzendmethode')
                            ->getStateUsing(fn($record) => $record->shippingMethod->name ?? 'Niet gevonden'),
                        TextEntry::make('subtotal')
                            ->label('Subtotaal')
                            ->money('EUR'),
                        TextEntry::make('discount')
                            ->label('Korting')
                            ->money('EUR'),
                        TextEntry::make('discountCode.code')
                            ->label('Kortingscode')
                            ->visible(fn($record) => $record->discountCode),
                        TextEntry::make('btw')
                            ->label('BTW')
                            ->money('EUR'),
                        KeyValueEntry::make('vat_percentages')
                            ->label('BTW percentages')
                            ->keyLabel('Percentage')
                            ->valueLabel('Bedrag')
                            ->getStateUsing(function ($record) {
                                $vatPercentages = [];
                                foreach ($record->vat_percentages ?: [] as $key => $vatPercentage) {
                                    $vatPercentages[number_format($key, 0) . '%'] = '€' . number_format($vatPercentage, 2, ',', '.');
                                }

                                return $vatPercentages;
                            }),
                        TextEntry::make('total')
                            ->label('Totaal')
                            ->money('EUR'),
                    ])
                    ->columns(4),
                Fieldset::make('Extra informatie')
                    ->schema($customOrderFields)
                    ->visible(count($customOrderFields))
                    ->columns(4),
            ]);
    }

    public function render()
    {
        return view('dashed-ecommerce-core::orders.components.infolists.plain-info-list');
    }
}
