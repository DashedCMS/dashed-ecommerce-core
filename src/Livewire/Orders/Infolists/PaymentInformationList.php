<?php

namespace Dashed\DashedEcommerceCore\Livewire\Orders\Infolists;

use Livewire\Component;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Contracts\HasSchemas;
use Dashed\DashedEcommerceCore\Models\Order;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Schemas\Concerns\InteractsWithSchemas;

class PaymentInformationList extends Component implements HasSchemas
{
    use InteractsWithSchemas;

    public Order $order;

    protected $listeners = [
        'refreshData' => '$refresh',
    ];

    public function mount($order)
    {
        $this->order = $order;
    }

    public function infolist(Schema $schema): Schema
    {
        $customOrderFields = [];

        foreach ($this->order->customOrderFields() as $label => $value) {
            // Unieke key per veld
            $key = 'custom_' . md5($label);
            $customOrderFields[] = TextEntry::make($key)
                ->label($label)
                ->state(fn () => $value);
        }

        return $schema
            ->record($this->order)
            ->components([
                Fieldset::make('payment_info')->columnSpanFull()
                    ->label('Betaal informatie')
                    ->schema([
                        TextEntry::make('order_origin')
                            ->label('Bestellingsherkomst'),

                        TextEntry::make('ip')
                            ->label('IP'),

                        TextEntry::make('note')
                            ->label('Notitie')
                            ->state(fn (Order $record) => $record->note ?: 'Geen notitie'),

                        IconEntry::make('marketing')
                            ->label('Marketing geaccepteerd')
                            ->trueIcon('heroicon-o-check-circle')
                            ->falseIcon('heroicon-o-x-circle'),

                        TextEntry::make('invoice_id')
                            ->label('Factuur ID'),

                        TextEntry::make('payment_method_name')
                            ->label('Betalingsmethode')
                            ->state(fn (Order $record) => $record->paymentMethod?->name ?? 'Niet gevonden'),

                        TextEntry::make('psp')
                            ->label('PSP')
                            ->visible(fn (Order $record) => (bool) $record->psp),

                        TextEntry::make('psp_id')
                            ->label('PSP ID')
                            ->visible(fn (Order $record) => (bool) $record->psp),

                        TextEntry::make('shipping_method_name')
                            ->label('Verzendmethode')
                            ->state(fn (Order $record) => $record->shippingMethod->name ?? 'Niet gevonden'),

                        TextEntry::make('subtotal')
                            ->label('Subtotaal')
                            ->money('EUR'),

                        TextEntry::make('discount')
                            ->label('Korting')
                            ->money('EUR'),

                        TextEntry::make('discountCode.code')
                            ->label('Kortingscode')
                            ->visible(fn (Order $record) => (bool) $record->discountCode),

                        TextEntry::make('btw')
                            ->label('BTW')
                            ->money('EUR'),

                        KeyValueEntry::make('vat_percentages')
                            ->label('BTW percentages')
                            ->keyLabel('Percentage')
                            ->valueLabel('Bedrag')
                            ->state(function (Order $record) {
                                $vatPercentages = [];
                                foreach ($record->vat_percentages ?: [] as $key => $amount) {
                                    $vatPercentages[number_format((float) $key, 0) . '%'] = '€' . number_format((float) $amount, 2, ',', '.');
                                }

                                return $vatPercentages;
                            }),

                        TextEntry::make('total')
                            ->label('Totaal')
                            ->money('EUR'),
                    ])
                    ->columns(4),

                Fieldset::make('extra_info')->columnSpanFull()
                    ->label('Extra informatie')
                    ->schema($customOrderFields)
                    ->visible(count($customOrderFields) > 0)
                    ->columns(4),
            ]);
    }

    public function render()
    {
        return view('dashed-ecommerce-core::orders.components.infolists.plain-info-list');
    }
}
