<?php

namespace Dashed\DashedEcommerceCore\Livewire\Orders\Infolists;

use Livewire\Component;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Contracts\HasSchemas;
use Dashed\DashedEcommerceCore\Models\Order;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Concerns\InteractsWithSchemas;

class ShippingInformationList extends Component implements HasSchemas
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
        $trackAndTraceSets = [];
        $i = 0;

        foreach ($this->order->trackAndTraces as $trackAndTrace) {
            $trackAndTraceSets[] = Fieldset::make('track_and_trace_' . $i)
                ->label('Track & Trace')
                ->columnSpanFull()
                ->schema([
                    TextEntry::make('supplier_' . $i)
                        ->hiddenLabel()
                        ->state('Via: ' . ($trackAndTrace->supplier ?? ''))
                        ->icon('heroicon-o-truck'),

                    TextEntry::make('code_' . $i)
                        ->hiddenLabel()
                        ->state(($trackAndTrace->delivery_company ?? '') . ': ' . ($trackAndTrace->code ?? ''))
                        ->url(fn () => $trackAndTrace->url ?: '#')
                        ->openUrlInNewTab()
                        ->icon('heroicon-o-envelope'),
                ]);

            $i++;
        }

        return $schema
            ->record($this->order)
            ->components([
                Fieldset::make('shipping_info')
                    ->label('Verzend informatie')
                    ->schema(array_merge([
                        TextEntry::make('shipping_address')
                            ->hiddenLabel()
                            ->state(fn (Order $record) => new HtmlString(
                                ($record->company_name ? $record->company_name . ' <br>' : '') .
                                "{$record->name}<br>{$record->street} {$record->house_nr}<br>{$record->city} {$record->zip_code}<br>{$record->country}"
                            )),

                        TextEntry::make('invoice_address')
                            ->hiddenLabel()
                            ->state(fn (Order $record) => new HtmlString(
                                ($record->company_name ? $record->company_name . ' <br>' : '') .
                                "{$record->name}<br>{$record->invoice_street} {$record->invoice_house_nr}<br>{$record->invoice_city} {$record->invoice_zip_code}<br>{$record->invoice_country}"
                            )),

                        Grid::make()
                            ->schema([
                                TextEntry::make('phone_number_entry')
                                    ->hiddenLabel()
                                    ->state(fn (Order $record) => $record->phone_number)
                                    ->url(fn (Order $record) => 'tel:' . $record->phone_number)
                                    ->badge()
                                    ->icon('heroicon-o-phone'),

                                TextEntry::make('email_entry')
                                    ->hiddenLabel()
                                    ->state(fn (Order $record) => $record->email)
                                    ->url(fn (Order $record) => 'mailto:' . $record->email)
                                    ->badge()
                                    ->icon('heroicon-o-envelope')
                                    ->columnSpanFull(),
                            ])
                            ->columnSpan(1),
                    ], $trackAndTraceSets))
                    ->columns(3),
            ]);
    }

    public function render()
    {
        return view('dashed-ecommerce-core::orders.components.infolists.plain-info-list');
    }
}
