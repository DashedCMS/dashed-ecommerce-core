<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources;

use UnitEnum;
use BackedEnum;
use Filament\Tables\Table;
use Filament\Resources\Resource;
use Filament\Actions\DeleteAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Dashed\DashedEcommerceCore\Models\StockNotification;
use Dashed\DashedCore\Classes\Actions\ActionGroups\ToolbarActions;
use Dashed\DashedEcommerceCore\Filament\Resources\StockNotificationResource\Pages\ListStockNotifications;

class StockNotificationResource extends Resource
{
    protected static ?string $model = StockNotification::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-bell-alert';
    protected static UnitEnum|string|null $navigationGroup = 'E-commerce';
    protected static ?string $navigationLabel = 'Terug-op-voorraad';
    protected static ?string $label = 'Terug-op-voorraad-melding';
    protected static ?string $pluralLabel = 'Terug-op-voorraad-meldingen';

    public static function canCreate(): bool
    {
        // Aanmeldingen ontstaan via de chat-AI of de webshop, niet handmatig.
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('product.name')
                    ->label('Product')
                    ->formatStateUsing(fn ($state) => is_array($state) ? (reset($state) ?: '—') : ($state ?: '—'))
                    ->searchable(),
                TextColumn::make('email')
                    ->label('E-mail')
                    ->searchable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->getStateUsing(fn (StockNotification $record) => $record->notified_at ? 'Verstuurd' : 'Openstaand')
                    ->color(fn (string $state) => $state === 'Verstuurd' ? 'success' : 'warning'),
                TextColumn::make('created_at')
                    ->label('Aangemeld op')
                    ->dateTime('d-m-Y H:i')
                    ->sortable(),
                TextColumn::make('notified_at')
                    ->label('Verstuurd op')
                    ->dateTime('d-m-Y H:i')
                    ->placeholder('—')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                TernaryFilter::make('notified_at')
                    ->label('Status')
                    ->placeholder('Alles')
                    ->trueLabel('Verstuurd')
                    ->falseLabel('Openstaand')
                    ->nullable(),
            ])
            ->recordActions([
                DeleteAction::make(),
            ])
            ->toolbarActions(ToolbarActions::getActions());
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStockNotifications::route('/'),
        ];
    }
}
