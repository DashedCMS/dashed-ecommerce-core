<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Filament\Resources\AutomationRuleResource\RelationManagers;

use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Resources\RelationManagers\RelationManager;
use Dashed\DashedEcommerceCore\Models\AutomationRuleRun;

/**
 * Read-only overzicht van het uitvoerlog van deze regel — zelfde opzet als
 * CartResource\RelationManagers\LogsRelationManager: geen header-/record-/
 * toolbar-acties, puur inzicht.
 */
class RunsRelationManager extends RelationManager
{
    protected static string $relationship = 'runs';

    protected static ?string $title = 'Recente runs';

    protected static ?string $modelLabel = 'run';

    protected static ?string $pluralModelLabel = 'runs';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                TextColumn::make('created_at')
                    ->label('Wanneer')
                    ->dateTime('d-m-Y H:i:s', 'Europe/Amsterdam')
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        AutomationRuleRun::STATUS_SUCCESS => 'Gelukt',
                        AutomationRuleRun::STATUS_FAILED => 'Mislukt',
                        AutomationRuleRun::STATUS_RUNNING => 'Bezig',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        AutomationRuleRun::STATUS_SUCCESS => 'success',
                        AutomationRuleRun::STATUS_FAILED => 'danger',
                        AutomationRuleRun::STATUS_RUNNING => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('subject_type')
                    ->label('Onderwerp')
                    ->formatStateUsing(fn (string $state): string => class_basename($state)),
                TextColumn::make('subject_id')
                    ->label('#'),
                TextColumn::make('error')
                    ->label('Foutmelding')
                    ->limit(60)
                    ->toggleable()
                    ->placeholder('-'),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        AutomationRuleRun::STATUS_SUCCESS => 'Gelukt',
                        AutomationRuleRun::STATUS_FAILED => 'Mislukt',
                        AutomationRuleRun::STATUS_RUNNING => 'Bezig',
                    ]),
            ])
            ->paginated([10, 25, 50])
            ->headerActions([])
            ->recordActions([])
            ->toolbarActions([])
            ->emptyStateHeading('Nog geen runs')
            ->emptyStateDescription('Zodra deze regel matcht op een trigger, verschijnt het resultaat hier.')
            ->emptyStateIcon('heroicon-o-clock');
    }

    public function isReadOnly(): bool
    {
        return true;
    }
}
