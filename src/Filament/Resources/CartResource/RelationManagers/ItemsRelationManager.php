<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources\CartResource\RelationManagers;

use Filament\Tables;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;

class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    protected static ?string $title = 'Items';

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('id')->sortable(),

                Tables\Columns\TextColumn::make('product_type')->label('Type')->badge()->sortable(),

                Tables\Columns\TextColumn::make('product_id')
                    ->label('Product ID')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('name')
                    ->label('Naam')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('quantity')->label('Qty')->sortable(),

                Tables\Columns\TextColumn::make('single_price')
                    ->label('Stuk')
                    ->money('EUR', locale: 'nl_NL')
                    ->sortable(),

                Tables\Columns\TextColumn::make('total_price')
                    ->label('Totaal')
                    ->money('EUR', locale: 'nl_NL')
                    ->sortable(),

                Tables\Columns\TextColumn::make('updated_at')->dateTime()->sortable(),
            ])
            ->actions([
                Tables\Actions\DeleteAction::make(),
            ]);
    }
}
