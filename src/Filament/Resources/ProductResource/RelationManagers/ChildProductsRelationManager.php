<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources\ProductResource\RelationManagers;

use Filament\Tables\Table;
use Filament\Actions\Action;
use Filament\Schemas\Schema;
use Filament\Actions\BulkAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Actions\ForceDeleteBulkAction;
use Illuminate\Database\Eloquent\Collection;
use Dashed\DashedEcommerceCore\Models\Product;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Resources\RelationManagers\RelationManager;
use Dashed\DashedEcommerceCore\Filament\Actions\BulkPriceUpdateBulkAction;
use Dashed\DashedEcommerceCore\Filament\Actions\BulkDeliveryTimeUpdateBulkAction;

class ChildProductsRelationManager extends RelationManager
{
    protected static string $relationship = 'childProducts';
    protected string $view = 'dashed-ecommerce-core::products.child-products.table';

    protected static ?string $recordTitleAttribute = 'name';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                //
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('Naam'))
                    ->searchable([
                        'name',
                        'short_description',
                        'description',
                        'search_terms',
                        'content',
                    ])
                    ->sortable(),
                TextColumn::make('total_purchases')
                    ->label(__('Aantal verkopen')),
                TextColumn::make('total_stock')
                    ->label(__('Voorraad')),
                ImageColumn::make('image')
                    ->getStateUsing(fn ($record) => $record->images ? mediaHelper()->getSingleMedia($record->images[0], 'original')->url : '')
                    ->label(''),
                IconColumn::make('indexable')
                    ->label(__('Tonen in overzicht'))
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->sortable(),
                IconColumn::make('status')
                    ->label(__('Status'))
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle'),
            ])
            ->recordActions([
                Action::make('quickActions')
                    ->button()
                    ->label(__('Quick'))
                    ->color('primary')
                    ->modalHeading(__('Snel bewerken'))
                    ->modalButton('Opslaan')
                    ->schema([
                        Section::make(__('Beheer de prijzen'))->columnSpanFull()
                            ->schema([
                                TextInput::make('price')
                                    ->label(__('Prijs van het product'))
                                    ->helperText(__('Voorbeeld: 10.25'))
                                    ->prefix('€')
                                    ->minValue(1)
                                    ->maxValue(100000)
                                    ->numeric()
                                    ->required()
                                    ->default(fn ($record) => $record->price),
                                TextInput::make('new_price')
                                    ->label(__('Vorige prijs (de hogere prijs)'))
                                    ->helperText(__('Voorbeeld: 14.25'))
                                    ->prefix('€')
                                    ->minValue(1)
                                    ->maxValue(100000)
                                    ->numeric()
                                    ->default(fn ($record) => $record->new_price),
                            ])
                            ->columns([
                                'default' => 1,
                                'lg' => 2,
                            ]),
                        Section::make(__('Voorraad beheren'))->columnSpanFull()
                            ->schema([
                                Toggle::make('use_stock')
                                    ->default(fn ($record) => $record->use_stock)
                                    ->label(__('Voorraad bijhouden'))
                                    ->reactive(),
                                TextInput::make('stock')
                                    ->default(fn ($record) => $record->stock)
                                    ->type('number')
                                    ->label(__('Hoeveel heb je van dit product op voorraad'))
                                    ->maxValue(100000)
                                    ->required()
                                    ->numeric()
                                    ->hidden(fn (Get $get) => ! $get('use_stock')),
                                Toggle::make('out_of_stock_sellable')
                                    ->default(fn ($record) => $record->out_of_stock_sellable)
                                    ->label(__('Product doorverkopen wanneer niet meer op voorraad (pre-orders)'))
                                    ->reactive()
                                    ->hidden(fn (Get $get) => ! $get('use_stock')),
                                DatePicker::make('expected_in_stock_date')
                                    ->default(fn ($record) => $record->expected_in_stock_date)
                                    ->label(__('Wanneer komt dit product weer op voorraad'))
                                    ->reactive()
                                    ->required()
                                    ->hidden(fn (Get $get) => ! $get('use_stock') || ! $get('out_of_stock_sellable')),
                                Toggle::make('low_stock_notification')
                                    ->default(fn ($record) => $record->low_stock_notification)
                                    ->label(__('Ik wil een melding krijgen als dit product laag op voorraad raakt'))
                                    ->reactive()
                                    ->hidden(fn (Get $get) => ! $get('use_stock')),
                                TextInput::make('low_stock_notification_limit')
                                    ->default(fn ($record) => $record->low_stock_notification_limit)
                                    ->label(__('Als de voorraad van dit product onder onderstaand nummer komt, krijg je een notificatie'))
                                    ->type('number')
                                    ->reactive()
                                    ->required()
                                    ->minValue(1)
                                    ->maxValue(100000)
                                    ->default(1)
                                    ->numeric()
                                    ->hidden(fn (Get $get) => ! $get('use_stock') || ! $get('low_stock_notification')),
                                Select::make('stock_status')
                                    ->default(fn ($record) => $record->stock_status ?: 'in_stock')
                                    ->label(__('Is dit product op voorraad'))
                                    ->options([
                                        'in_stock' => __('Op voorraad'),
                                        'out_of_stock' => __('Uitverkocht'),
                                    ])
//                                ->default('in_stock')
                                    ->required()
                                    ->hidden(fn (Get $get) => $get('use_stock')),
                                Toggle::make('limit_purchases_per_customer')
                                    ->default(fn ($record) => $record->limit_purchases_per_customer)
                                    ->label(__('Dit product mag maar een x aantal keer per bestelling gekocht worden'))
                                    ->reactive(),
                                TextInput::make('limit_purchases_per_customer_limit')
                                    ->default(fn ($record) => $record->limit_purchases_per_customer_limit)
                                    ->type('number')
                                    ->label(__('Hoeveel mag dit product gekocht worden per bestelling'))
                                    ->minValue(1)
                                    ->maxValue(100000)
                                    ->default(1)
                                    ->numeric()
                                    ->required()
                                    ->hidden(fn (Get $get) => ! $get('limit_purchases_per_customer')),
                            ]),
                    ])
                    ->action(function (Product $record, array $data): void {
                        foreach ($data as $key => $value) {
                            $record[$key] = $value;
                        }
                        $record->save();

                        Notification::make()
                            ->title(__('Het product is aangepast'))
                            ->success()
                            ->send();
                    }),
                Action::make('edit')
                    ->label(__('Bewerken'))
                    ->url(fn (Product $record) => route('filament.dashed.resources.products.edit', [$record])),
                DeleteAction::make(),
                RestoreAction::make(),
                ForceDeleteAction::make(),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->toolbarActions([
                BulkPriceUpdateBulkAction::make(),
                BulkDeliveryTimeUpdateBulkAction::make(),
                BulkAction::make('changePublicStatus')
                    ->color('primary')
                    ->label(__('Verander publieke status'))
                    ->schema([
                        Toggle::make('public')
                            ->label(__('Openbaar'))
                            ->default(1),
                    ])
                    ->action(function (Collection $records, array $data): void {
                        foreach ($records as $record) {
                            $record->public = $data['public'];
                            $record->save();
                        }

                        Notification::make()
                            ->title(__('Het product is aangepast'))
                            ->success()
                            ->send();
                    })
                    ->deselectRecordsAfterCompletion(),
                DeleteBulkAction::make(),
                RestoreBulkAction::make(),
                ForceDeleteBulkAction::make(),
            ])
            ->headerActions([
                \Filament\Actions\Action::make('Aanmaken')
                    ->button()
                    ->url(fn ($record) => route('filament.dashed.resources.products.create')),
            ]);
    }
}
