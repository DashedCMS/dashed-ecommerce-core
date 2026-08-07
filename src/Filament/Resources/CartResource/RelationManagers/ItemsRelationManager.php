<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources\CartResource\RelationManagers;

use Filament\Tables;
use Filament\Tables\Table;
use Filament\Actions\DeleteAction;
use Filament\Resources\RelationManagers\RelationManager;

class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    protected static ?string $title = 'Items';

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->sortable(),

                Tables\Columns\TextColumn::make('product_id')
                    ->label(__('Product ID'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('name')
                    ->label(__('Naam'))
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('quantity')->label(__('Aantal'))->sortable(),

                Tables\Columns\TextColumn::make('unit_price')
                    ->label(__('Per stuk'))
                    ->money('EUR', locale: 'nl_NL')
                    ->sortable(),

                Tables\Columns\TextColumn::make('total_price')
                    ->getStateUsing(fn ($record) => $record->unit_price * $record->quantity)
                    ->label(__('Totaal'))
                    ->money('EUR', locale: 'nl_NL'),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label(__('Bijgewerkt op'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->actions([
                DeleteAction::make(),
            ]);
    }
}
