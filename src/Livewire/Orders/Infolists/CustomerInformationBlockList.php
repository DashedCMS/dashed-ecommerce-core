<?php

namespace Dashed\DashedEcommerceCore\Livewire\Orders\Infolists;

use Livewire\Component;
use Filament\Actions\Action;
use Filament\Schemas\Schema;
use Dashed\DashedCore\Classes\Helper;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Fieldset;
use Filament\Actions\Contracts\HasActions;
use Filament\Schemas\Contracts\HasSchemas;
use Dashed\DashedEcommerceCore\Models\Order;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ImageEntry;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Dashed\DashedEcommerceCore\Classes\CustomerHistory;
use Dashed\DashedEcommerceCore\Filament\Resources\OrderResource;

class CustomerInformationBlockList extends Component implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;

    public Order $order;

    private ?CustomerHistory $cachedHistory = null;

    protected function history(): CustomerHistory
    {
        return $this->cachedHistory ??= new CustomerHistory($this->order);
    }

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
                                    ->url(fn ($record) => 'tel:'.$record->phone_number)
                                    ->badge()
                                    ->icon('heroicon-o-phone')
                                    ->hiddenLabel(),
                            ])
                            ->columnSpan(1)
                            ->columns(1),
                        TextEntry::make('email')
                            ->label('Email')
                            ->url(fn ($record) => 'mailto:'.$record->email)
                            ->badge()
                            ->columnSpanFull()
                            ->icon('heroicon-o-envelope')
                            ->hiddenLabel(),
                        TextEntry::make('customer_history_text')
                            ->label('Eerdere bestellingen')
                            ->columnSpanFull()
                            ->getStateUsing(function () {
                                $other = $this->history()->otherCount();

                                return $other === 0
                                    ? 'Dit is de eerste bestelling van deze klant'
                                    : 'Deze klant heeft al '.$other.' andere bestelling(en)';
                            })
                            ->visible(fn () => $this->history()->matchKey() !== null)
                            ->helperText('Op basis van gebruiker, e-mail of voor+achternaam')
                            ->hintAction(
                                Action::make('customer_history')
                                    ->label('Bekijk details')
                                    ->icon('heroicon-o-chart-bar')
                                    ->modalHeading(fn ($record) => 'Bestelhistorie: '.($record->name ?: $record->email))
                                    ->modalWidth('5xl')
                                    ->modalContent(fn () => view(
                                        'dashed-ecommerce-core::filament.orders.customer-history-modal',
                                        ['history' => $this->history()],
                                    ))
                                    ->modalSubmitAction(false)
                                    ->modalCancelActionLabel('Sluiten')
                                    ->extraModalFooterActions(fn ($record) => [
                                        Action::make('view_filtered_orders')
                                            ->label('Bekijk alle bestellingen →')
                                            ->color('primary')
                                            ->url(fn () => OrderResource::getUrl('index', [
                                                'tableFilters' => [
                                                    'customer_match' => [
                                                        'value' => 'order:'.$record->id,
                                                    ],
                                                ],
                                            ]))
                                            ->openUrlInNewTab(false),
                                    ])
                                    ->visible(fn () => $this->history()->otherCount() > 0),
                            ),
                    ])
                    ->columns(2),
            ]);
    }

    public function render()
    {
        return view('dashed-ecommerce-core::orders.components.infolists.plain-info-list');
    }
}
