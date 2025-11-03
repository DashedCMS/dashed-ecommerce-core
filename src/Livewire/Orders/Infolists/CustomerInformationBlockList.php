<?php

namespace Dashed\DashedEcommerceCore\Livewire\Orders\Infolists;

use Livewire\Component;
use Filament\Schemas\Schema;
use Dashed\DashedCore\Classes\Helper;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Contracts\HasSchemas;
use Dashed\DashedEcommerceCore\Models\Order;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ImageEntry;
use Filament\Schemas\Concerns\InteractsWithSchemas;

class CustomerInformationBlockList extends Component implements HasSchemas
{
    use InteractsWithSchemas;

    public Order $order;

    protected $listeners = [
        'refreshData' => '$refresh',
    ];

    public function mount($order): void
    {
        $this->order = $order;
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->record($this->order)
            ->schema([
                Fieldset::make('Klant')
                    ->schema([
                        ImageEntry::make('image')
                            ->getStateUsing(fn ($record) => Helper::getProfilePicture($record->email))
                            ->circular()
                            ->hiddenLabel()
                            ->imageSize('md'),
                        Grid::make()
                            ->schema([
                                TextEntry::make('name')
                                    ->label('Naam')
                                    ->hiddenLabel(),
                                TextEntry::make('phone_number')
                                    ->label('Telefoonnummer')
                                    ->url(fn ($record) => 'tel:' . $record->phone_number)
                                    ->badge()
                                    ->icon('heroicon-o-phone')
                                    ->hiddenLabel(),
                            ])
                            ->columnSpan(1)
                            ->columns(1),
                        TextEntry::make('email')
                            ->label('Email')
                            ->url(fn ($record) => 'mailto:' . $record->email)
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
