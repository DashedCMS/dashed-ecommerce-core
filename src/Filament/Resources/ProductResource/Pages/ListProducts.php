<?php

namespace Qubiqx\QcommerceEcommerceCore\Filament\Resources\ProductResource\Pages;

use Filament\Tables\Actions\Action;
use Filament\Tables\Filters\Filter;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Section;
use Filament\Tables\Actions\BulkAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Qubiqx\QcommerceEcommerceCore\Models\Product;
use Qubiqx\QcommerceEcommerceCore\Models\ProductCategory;
use Filament\Resources\Pages\ListRecords\Concerns\Translatable;
use Qubiqx\QcommerceEcommerceCore\Filament\Resources\ProductResource;

class ListProducts extends ListRecords
{
    use Translatable;

    protected static string $resource = ProductResource::class;

//    protected function getTableQuery(): Builder
//    {
//        return Product::query()->topLevel();
//    }

    protected function getTableActions(): array
    {
        return array_merge(parent::getTableActions(), [
            Action::make('quickActions')
                ->button()
                ->label('Quick')
                ->color('primary')
                ->modalHeading('Snel bewerken')
                ->modalButton('Opslaan')
                ->form([
                    Section::make('Beheer de prijzen')
                        ->schema([
                            TextInput::make('price')
                                ->label('Prijs van het product')
                                ->helperText('Voorbeeld: 10.25')
                                ->prefix('€')
                                ->minValue(1)
                                ->maxValue(100000)
                                ->required()
                                ->default(fn ($record) => $record->price)
                                ->rules(['required',
                                    'numeric',
                                    'min:1',
                                    'max:100000',
                                ]),
                            TextInput::make('new_price')
                                ->label('Vorige prijs (de hogere prijs)')
                                ->helperText('Voorbeeld: 14.25')
                                ->prefix('€')
                                ->minValue(1)
                                ->maxValue(100000)
                                ->default(fn ($record) => $record->new_price)
                                ->rules(['numeric',
                                    'min:1',
                                    'max:100000',
                                ]),
                        ])
                        ->columns([
                            'default' => 1,
                            'lg' => 2,
                        ]),
                    Section::make('Voorraad beheren')
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
                                ->rules([
                                    'required',
                                    'numeric',
                                    'max:100000',
                                ])
                                ->hidden(fn (\Closure $get) => ! $get('use_stock')),
                            Toggle::make('out_of_stock_sellable')
                                ->default(fn ($record) => $record->out_of_stock_sellable)
                                ->label('Product doorverkopen wanneer niet meer op voorraad (pre-orders)')
                                ->reactive()
                                ->hidden(fn (\Closure $get) => ! $get('use_stock')),
                            DatePicker::make('expected_in_stock_date')
                                ->default(fn ($record) => $record->expected_in_stock_date)
                                ->label('Wanneer komt dit product weer op voorraad')
                                ->reactive()
                                ->required()
                                ->hidden(fn (\Closure $get) => ! $get('use_stock') || ! $get('out_of_stock_sellable')),
                            Toggle::make('low_stock_notification')
                                ->default(fn ($record) => $record->low_stock_notification)
                                ->label('Ik wil een melding krijgen als dit product laag op voorraad raakt')
                                ->reactive()
                                ->hidden(fn (\Closure $get) => ! $get('use_stock')),
                            TextInput::make('low_stock_notification_limit')
                                ->default(fn ($record) => $record->low_stock_notification_limit)
                                ->label('Als de voorraad van dit product onder onderstaand nummer komt, krijg je een notificatie')
                                ->type('number')
                                ->reactive()
                                ->required()
                                ->minValue(1)
                                ->maxValue(100000)
                                ->default(1)
                                ->required()
                                ->rules([
                                    'required',
                                    'numeric',
                                    'min:1',
                                    'max:100000',
                                ])
                                ->hidden(fn (\Closure $get) => ! $get('use_stock') || ! $get('low_stock_notification')),
                            Select::make('stock_status')
                                ->default(fn ($record) => $record->stock_status ?: 'in_stock')
                                ->label('Is dit product op voorraad')
                                ->options([
                                    'in_stock' => 'Op voorraad',
                                    'out_of_stock' => 'Uitverkocht',
                                ])
                                ->required()
                                ->rules([
                                    'required',
                                ])
                                ->hidden(fn (\Closure $get) => $get('use_stock')),
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
                                ->required()
                                ->rules([
                                    'required',
                                    'numeric',
                                    'min:1',
                                    'max:100000',
                                ])
                                ->hidden(fn (\Closure $get) => ! $get('limit_purchases_per_customer')),
                        ]),
                ])
                ->action(function (Product $record, array $data): void {
                    foreach ($data as $key => $value) {
                        $record[$key] = $value;
                    }
                    $record->save();
                })
                ->hidden(fn ($record) => $record->type == 'variable' && ! $record->parent_product_id),
        ]);
    }

    protected function getTableFilters(): array
    {
        return [
            Filter::make('onlyParentProducts')
                ->form([
                    Toggle::make('value')
                        ->label('Alleen hoofd producten')
                        ->default(1),
                ])
                ->query(function (Builder $query, array $data): Builder {
                    return $query
                        ->when(
                            $data['value'],
                            fn (Builder $query, $value): Builder => $query->topLevel(),
                        );
                }),
            Filter::make('categories')
                ->form([
                    Select::make('categories')
                        ->multiple()
                        ->label('Categorieen')
                        ->options(ProductCategory::all()->pluck('name', 'id')),
                ])
                ->query(function (Builder $query, array $data): Builder {
                    if (! $data['categories']) {
                        return $query;
                    }

                    return $query->whereHas('productCategories', fn (Builder $query) => $query->whereIn('product_category_id', $data['categories']));
                }),
        ];
    }

    protected function getTableBulkActions(): array
    {
        return array_merge(parent::getTableBulkActions(), [
            BulkAction::make('changePrice')
                ->color('primary')
                ->label('Verander prijzen')
                ->form([
                    TextInput::make('price')
                        ->label('Prijs van het product')
                        ->helperText('Voorbeeld: 10.25')
                        ->prefix('€')
                        ->minValue(1)
                        ->maxValue(100000)
                        ->required()
                        ->rules(['required',
                            'numeric',
                            'min:1',
                            'max:100000',
                        ]),
                    TextInput::make('new_price')
                        ->label('Vorige prijs (de hogere prijs)')
                        ->helperText('Voorbeeld: 14.25')
                        ->prefix('€')
                        ->minValue(1)
                        ->maxValue(100000)
                        ->rules(['numeric',
                            'min:1',
                            'max:100000',
                        ]),
                ])
                ->action(function (Collection $records, array $data): void {
                    foreach ($records as $record) {
                        $record->price = $data['price'];
                        $record->new_price = $data['new_price'];
                        $record->save();
                    }
                })
                ->deselectRecordsAfterCompletion(),
            BulkAction::make('changePublicStatus')
                ->color('primary')
                ->label('Verander publieke status')
                ->form([
                    Toggle::make('public')
                        ->label('Openbaar')
                        ->default(1),
                ])
                ->action(function (Collection $records, array $data): void {
                    foreach ($records as $record) {
                        $record->public = $data['public'];
                        $record->save();
                    }
                })
                ->deselectRecordsAfterCompletion(),
        ]);
    }
}
