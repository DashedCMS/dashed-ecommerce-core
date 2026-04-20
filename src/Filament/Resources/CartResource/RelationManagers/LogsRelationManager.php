<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources\CartResource\RelationManagers;

use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Resources\RelationManagers\RelationManager;

class LogsRelationManager extends RelationManager
{
    protected static string $relationship = 'logs';

    protected static ?string $title = 'Activiteit';

    protected static ?string $modelLabel = 'logregel';

    protected static ?string $pluralModelLabel = 'logregels';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('message')
            ->columns([
                TextColumn::make('created_at')
                    ->label('Wanneer')
                    ->dateTime('d-m-Y H:i:s', 'Europe/Amsterdam')
                    ->sortable(),
                TextColumn::make('event')
                    ->label('Event')
                    ->badge()
                    ->color(fn (string $state): string => match (true) {
                        str_starts_with($state, 'cart.product.') => 'primary',
                        str_starts_with($state, 'cart.abandoned-email.') => 'warning',
                        $state === 'cart.converted-to-order' => 'success',
                        $state === 'cart.emptied' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('message')
                    ->label('Bericht')
                    ->wrap(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('event')
                    ->options(fn () => \Dashed\DashedEcommerceCore\Models\CartLog::query()
                        ->whereNotNull('event')
                        ->distinct()
                        ->pluck('event', 'event')
                        ->toArray())
                    ->label('Event type'),
            ])
            ->paginated([25, 50, 100])
            ->headerActions([])
            ->recordActions([])
            ->toolbarActions([]);
    }

    public function isReadOnly(): bool
    {
        return true;
    }
}
