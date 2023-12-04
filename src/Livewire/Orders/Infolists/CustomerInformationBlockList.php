<?php

namespace Dashed\DashedEcommerceCore\Livewire\Orders\Infolists;

use Livewire\Component;
use Filament\Infolists\Infolist;
use Dashed\DashedCore\Classes\Helper;
use Filament\Forms\Contracts\HasForms;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\Fieldset;
use Dashed\DashedEcommerceCore\Models\Order;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Contracts\HasInfolists;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Infolists\Concerns\InteractsWithInfolists;

class CustomerInformationBlockList extends Component implements HasForms, HasInfolists
{
    use InteractsWithForms;
    use InteractsWithInfolists;

    protected $listeners = [
        'refreshData' => '$refresh',
    ];

    public Order $order;

    public function mount($order)
    {
        $this->order = $order;
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->record($this->order)
            ->schema([
                Fieldset::make('Klant')
                    ->schema([
                        ImageEntry::make('image')
                            ->getStateUsing(fn($record) => Helper::getProfilePicture($record->email))
                            ->circular()
                            ->hiddenLabel()
                            ->size('md'),
                        Grid::make()
                            ->schema([
                                TextEntry::make('name')
                                    ->label('Naam')
                                    ->hiddenLabel(),
                                TextEntry::make('phone_number')
                                    ->label('Telefoonnummer')
                                    ->url(fn($record) => 'tel:' . $record->phone_number)
                                    ->badge()
                                    ->icon('heroicon-o-phone')
                                    ->hiddenLabel(),
                            ])
                            ->columnSpan(1)
                            ->columns(1),
                        TextEntry::make('email')
                            ->label('Email')
                            ->url(fn($record) => 'mailto:' . $record->email)
                            ->badge()
                            ->columnSpanFull()
                            ->icon('heroicon-o-envelope')
                            ->hiddenLabel(),
                    ])
                    ->columns(2),
            ]);
    }

    public function render()
    {
        return view('dashed-ecommerce-core::orders.components.infolists.plain-info-list');
    }
}
