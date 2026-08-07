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
                Fieldset::make(__('payment_info'))->columnSpanFull()
                    ->label(__('Betaal informatie'))
                    ->schema([
                        TextEntry::make('order_origin')
                            ->label(__('Bestellingsherkomst')),

                        TextEntry::make('ip')
                            ->label(__('IP')),

                        TextEntry::make('note')
                            ->label(__('Notitie'))
                            ->state(fn (Order $record) => $record->note ?: 'Geen notitie'),

                        IconEntry::make('marketing')
                            ->label(__('Marketing geaccepteerd'))
                            ->trueIcon('heroicon-o-check-circle')
                            ->falseIcon('heroicon-o-x-circle'),

                        TextEntry::make('invoice_id')
                            ->label(__('Factuur ID')),

                        TextEntry::make('payment_method_name')
                            ->label(__('Betaalmethode'))
                            ->state(fn (Order $record) => $record->mainPaymentMethod?->name ?? 'Niet gevonden'),

                        TextEntry::make('psp')
                            ->label(__('PSP'))
                            ->visible(fn (Order $record) => (bool) $record->psp),

                        TextEntry::make('psp_id')
                            ->label(__('PSP ID'))
                            ->visible(fn (Order $record) => (bool) $record->psp),

                        TextEntry::make('shipping_method_name')
                            ->label(__('Verzendmethode'))
                            ->state(fn (Order $record) => $record->shippingMethod->name ?? 'Niet gevonden'),

                        TextEntry::make('subtotal')
                            ->label(__('Subtotaal'))
                            ->money('EUR'),

                        TextEntry::make('discount')
                            ->label(__('Korting'))
                            ->money('EUR'),

                        TextEntry::make('discountCode.code')
                            ->label(__('Kortingscode'))
                            ->visible(fn (Order $record) => (bool) $record->discountCode),

                        TextEntry::make('btw')
                            ->label(__('BTW'))
                            ->money('EUR'),

                        KeyValueEntry::make('vat_percentages')
                            ->label(__('BTW percentages'))
                            ->keyLabel('Percentage')
                            ->valueLabel('Bedrag')
                            ->state(function (Order $record) {
                                $vatPercentages = [];
                                foreach ($record->vat_percentages ?: [] as $key => $amount) {
                                    $vatPercentages[number_format((float) $key, 0) . '%'] = '€' . number_format((float) $amount, 2, ',', '.');
                                }

                                return $vatPercentages;
                            }),

                        TextEntry::make('vat_reverse_charge')
                            ->label(__('BTW verlegd'))
                        ->getStateUsing(fn ($record) => $record->vat_reverse_charge ? 'Ja' : 'Nee'),

                        TextEntry::make('total')
                            ->label(__('Totaal'))
                            ->money('EUR'),
                    ])
                    ->columns(4),

                Fieldset::make(__('extra_info'))->columnSpanFull()
                    ->label(__('Extra informatie'))
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
