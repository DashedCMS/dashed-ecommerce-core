<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources\ProductGroupResource\RelationManagers;

use Filament\Tables\Table;
use Filament\Actions\Action;
use Filament\Schemas\Schema;
use Filament\Actions\BulkAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Tables\Columns\ImageColumn;
use Illuminate\Database\Eloquent\Collection;
use Dashed\DashedEcommerceCore\Models\Product;
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
                    ->fillForm(function (Product $record) {
                        return [
                            'price' => $record->price,
                            'new_price' => $record->new_price,
                            'use_stock' => $record->use_stock,
                            'limit_purchases_per_customer' => $record->limit_purchases_per_customer,
                            'out_of_stock_sellable' => $record->out_of_stock_sellable,
                            'low_stock_notification' => $record->low_stock_notification,
                            'stock' => $record->stock,
                            'expected_in_stock_date' => $record->expected_in_stock_date,
                            'expected_delivery_in_days' => $record->expected_delivery_in_days,
                            'low_stock_notification_limit' => $record->low_stock_notification_limit,
                            'stock_status' => $record->stock_status,
                            'limit_purchases_per_customer_limit' => $record->limit_purchases_per_customer_limit,
                            'fulfillment_provider' => $record->fulfillment_provider,
                        ];
                    })
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
                        Section::make('Voorraad beheren')
                            ->schema(Product::stockFilamentSchema())
                            ->columns([
                                'default' => 1,
                                'lg' => 2,
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
