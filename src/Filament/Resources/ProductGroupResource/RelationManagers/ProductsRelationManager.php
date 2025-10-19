<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources\ProductGroupResource\RelationManagers;

use Filament\Tables\Table;
use Filament\Actions\Action;
use Filament\Schemas\Schema;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Tables\Columns\ImageColumn;
use Filament\Forms\Components\DatePicker;
use Illuminate\Database\Eloquent\Collection;
use Dashed\DashedEcommerceCore\Models\Product;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Resources\RelationManagers\RelationManager;

class ProductsRelationManager extends RelationManager
{
    protected static string $relationship = 'products';

    protected static ?string $recordTitleAttribute = 'name';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                TextInput::make('name')
                    ->label('Naam')
                    ->maxLength(255)
                    ->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        $ownerRecord = $this->getOwnerRecord();

        return $table
            ->columns([
                ImageColumn::make('image')
                    ->getStateUsing(fn ($record) => $record->images ? (mediaHelper()->getSingleMedia($record->images[0], 'original')->url ?? '') : ($record->productGroup->images ? (mediaHelper()->getSingleMedia($record->productGroup->images[0], 'original')->url ?? '') : null))
                    ->label(''),
                TextColumn::make('name')
                    ->label('Naam')
                    ->searchable([
                        'name',
                        'short_description',
                        'description',
                        'search_terms',
                        'content',
                    ])
                    ->sortable(),
                TextColumn::make('total_purchases')
                    ->label('Aantal verkopen'),
                IconColumn::make('indexable')
                    ->label('Tonen in overzicht')
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->sortable(),
                IconColumn::make('status')
                    ->label('Status')
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle'),
            ])
            ->recordActions([
                Action::make('quickActions')
                    ->button()
                    ->label('Snelle acties')
                    ->color('primary')
                    ->modalHeading('Snel bewerken')
                    ->modalSubmitActionLabel('Opslaan')
                    ->schema([
                        Section::make('Beheer de prijzen')->columnSpanFull()
                            ->schema([
                                TextInput::make('price')
                                    ->label('Prijs van het product')
                                    ->helperText('Voorbeeld: 10.25')
                                    ->prefix('€')
                                    ->minValue(1)
                                    ->maxValue(100000)
                                    ->numeric()
                                    ->required()
                                    ->default(fn ($record) => $record->price),
                                TextInput::make('new_price')
                                    ->label('Vorige prijs (de hogere prijs)')
                                    ->helperText('Voorbeeld: 14.25')
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
                        Section::make('Voorraad beheren')->columnSpanFull()
                            ->schema([
                                Toggle::make('use_stock')
                                    ->default(fn ($record) => $record->use_stock)
                                    ->label('Voorraad bijhouden')
                                    ->reactive(),
                                TextInput::make('stock')
                                    ->default(fn ($record) => $record->stock)
                                    ->type('number')
                                    ->label('Hoeveel heb je van dit product op voorraad')
                                    ->maxValue(100000)
                                    ->required()
                                    ->numeric()
                                    ->hidden(fn (Get $get) => ! $get('use_stock')),
                                Toggle::make('out_of_stock_sellable')
                                    ->default(fn ($record) => $record->out_of_stock_sellable)
                                    ->label('Product doorverkopen wanneer niet meer op voorraad (pre-orders)')
                                    ->reactive()
                                    ->hidden(fn (Get $get) => ! $get('use_stock')),
                                DatePicker::make('expected_in_stock_date')
                                    ->default(fn ($record) => $record->expected_in_stock_date)
                                    ->label('Wanneer komt dit product weer op voorraad')
                                    ->reactive()
                                    ->required()
                                    ->hidden(fn (Get $get) => ! $get('use_stock') || ! $get('out_of_stock_sellable')),
                                Toggle::make('low_stock_notification')
                                    ->default(fn ($record) => $record->low_stock_notification)
                                    ->label('Ik wil een melding krijgen als dit product laag op voorraad raakt')
                                    ->reactive()
                                    ->hidden(fn (Get $get) => ! $get('use_stock')),
                                TextInput::make('low_stock_notification_limit')
                                    ->default(fn ($record) => $record->low_stock_notification_limit)
                                    ->label('Als de voorraad van dit product onder onderstaand nummer komt, krijg je een notificatie')
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
                                    ->label('Is dit product op voorraad')
                                    ->options([
                                        'in_stock' => 'Op voorraad',
                                        'out_of_stock' => 'Uitverkocht',
                                    ])
//                                ->default('in_stock')
                                    ->required()
                                    ->hidden(fn (Get $get) => $get('use_stock')),
                                Toggle::make('limit_purchases_per_customer')
                                    ->default(fn ($record) => $record->limit_purchases_per_customer)
                                    ->label('Dit product mag maar een x aantal keer per bestelling gekocht worden')
                                    ->reactive(),
                                TextInput::make('limit_purchases_per_customer_limit')
                                    ->default(fn ($record) => $record->limit_purchases_per_customer_limit)
                                    ->type('number')
                                    ->label('Hoeveel mag dit product gekocht worden per bestelling')
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
                            ->title('Het product is aangepast')
                            ->success()
                            ->send();
                    }),
                Action::make('edit')
                    ->label('Bewerken')
                    ->url(fn (Product $record) => route('filament.dashed.resources.products.edit', [$record])),
            ])
            ->filters([
                //
            ])
            ->toolbarActions([
                BulkAction::make('changePrice')
                    ->color('primary')
                    ->label('Verander prijzen')
                    ->schema([
                        TextInput::make('price')
                            ->label('Prijs van het product')
                            ->helperText('Voorbeeld: 10.25')
                            ->prefix('€')
                            ->minValue(1)
                            ->maxValue(100000)
                            ->required()
                            ->numeric(),
                        TextInput::make('new_price')
                            ->label('Vorige prijs (de hogere prijs)')
                            ->helperText('Voorbeeld: 14.25')
                            ->prefix('€')
                            ->minValue(1)
                            ->maxValue(100000)
                            ->numeric(),
                    ])
                    ->action(function (Collection $records, array $data): void {
                        foreach ($records as $record) {
                            $record->price = $data['price'];
                            $record->new_price = $data['new_price'];
                            $record->save();
                        }

                        Notification::make()
                            ->title('De producten zijn aangepast')
                            ->success()
                            ->send();
                    })
                    ->deselectRecordsAfterCompletion(),
                BulkAction::make('public')
                    ->color('primary')
                    ->label('Openbaar maken')
                    ->action(function (Collection $records, array $data): void {
                        foreach ($records as $record) {
                            $record->public = 1;
                            $record->save();
                        }

                        Notification::make()
                            ->title('De producten zijn aangepast')
                            ->success()
                            ->send();
                    })
                    ->deselectRecordsAfterCompletion(),
                BulkAction::make('hidden')
                    ->color('primary')
                    ->label('Verbergen')
                    ->action(function (Collection $records, array $data): void {
                        foreach ($records as $record) {
                            $record->public = 0;
                            $record->save();
                        }

                        Notification::make()
                            ->title('De producten zijn aangepast')
                            ->success()
                            ->send();
                    })
                    ->deselectRecordsAfterCompletion(),
                DeleteBulkAction::make(),
            ])
            ->headerActions([
                \Filament\Actions\Action::make('Product aanmaken')
                    ->button()
                    ->url(fn () => route('filament.dashed.resources.products.create') . '?productGroupId=' . $ownerRecord->id),
            ]);
    }
}
