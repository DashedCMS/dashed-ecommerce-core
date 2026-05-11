<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources\DiscountCodeResource\RelationManagers;

use Filament\Tables;
use Filament\Tables\Table;
use Filament\Actions\ViewAction;
use Filament\Resources\RelationManagers\RelationManager;
use Dashed\DashedEcommerceCore\Filament\Resources\OrderResource;

class OrdersRelationManager extends RelationManager
{
    protected static string $relationship = 'orders';

    protected static ?string $title = 'Gekoppelde bestellingen';

    protected static ?string $recordTitleAttribute = 'invoice_id';

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('invoice_id')
                    ->label('Factuur')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('name')
                    ->label('Klant')
                    ->getStateUsing(fn ($record) => $record->name ?: ($record->email ?: '-'))
                    ->searchable(['first_name', 'last_name', 'email'])
                    ->wrap(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'paid' => 'success',
                        'pending' => 'warning',
                        'cancelled', 'declined' => 'danger',
                        'concept' => 'gray',
                        default => 'gray',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('fulfillment_status')
                    ->label('Fulfillment')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'handled' => 'success',
                        'unhandled' => 'warning',
                        'in_treatment', 'packed', 'ready_for_pickup' => 'info',
                        'shipped' => 'primary',
                        default => 'gray',
                    })
                    ->toggleable(),

                Tables\Columns\TextColumn::make('discount')
                    ->label('Korting')
                    ->money('EUR', locale: 'nl_NL')
                    ->sortable(),

                Tables\Columns\TextColumn::make('total')
                    ->label('Totaal')
                    ->money('EUR', locale: 'nl_NL')
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Besteld op')
                    ->dateTime('d-m-Y H:i')
                    ->sortable(),
            ])
            ->recordActions([
                ViewAction::make()
                    ->url(fn ($record) => OrderResource::getUrl('view', ['record' => $record])),
            ])
            ->recordUrl(fn ($record) => OrderResource::getUrl('view', ['record' => $record]))
            ->headerActions([])
            ->paginated([10, 25, 50]);
    }
}
