<?php

namespace Dashed\DashedEcommerceCore\Livewire\Orders\Infolists;

use Livewire\Component;
use Filament\Infolists\Infolist;
use Illuminate\Support\HtmlString;
use Filament\Forms\Contracts\HasForms;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\Fieldset;
use Dashed\DashedEcommerceCore\Models\Order;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Contracts\HasInfolists;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Infolists\Concerns\InteractsWithInfolists;

class ShippingInformationList extends Component implements HasForms, HasInfolists
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
        return $infolist
            ->record($this->order)
            ->schema([
                Fieldset::make('Verzend informatie')
                    ->schema([
                        TextEntry::make('shippingAddress')
                            ->label('Verzendadres')
                            ->getStateUsing(fn ($record) => new HtmlString(($record->company_name ? $record->company_name . ' <br>' : '') . "$record->name<br>$record->street $record->house_nr<br>$record->city $record->zip_code<br>$record->country")),
                        TextEntry::make('invoiceAddress')
                            ->label('Factuuradres')
                            ->getStateUsing(fn ($record) => new HtmlString(($record->company_name ? $record->company_name . ' <br>' : '') . "$record->name<br>$record->invoice_street $record->invoice_house_nr<br>$record->invoice_city $record->invoice_zip_code<br>$record->invoice_country")),
                        Grid::make()
                            ->schema([
                                TextEntry::make('phone_number')
                                    ->label('Telefoonnummer')
                                    ->url(fn ($record) => 'tel:' . $record->phone_number)
                                    ->badge()
                                    ->icon('heroicon-o-phone')
                                    ->hiddenLabel(),
                                TextEntry::make('email')
                                    ->label('Email')
                                    ->url(fn ($record) => 'mailto:' . $record->email)
                                    ->badge()
                                    ->columnSpanFull()
                                    ->icon('heroicon-o-envelope')
                                    ->hiddenLabel(),
                            ])
                        ->columnSpan(1),
                    ])
                    ->columns(3),
            ]);
    }

    public function render()
    {
        return view('dashed-ecommerce-core::orders.components.infolists.plain-info-list');
    }
}
