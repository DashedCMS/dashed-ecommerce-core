<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources\ProductFilterResource\RelationManagers;

use Filament\Tables\Table;
use Filament\Actions\Action;
use Filament\Schemas\Schema;
use Filament\Actions\DeleteAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Resources\RelationManagers\RelationManager;
use Dashed\DashedEcommerceCore\Models\ProductFilterOption;

class ProductFilterOptionRelationManager extends RelationManager
{
    protected static string $relationship = 'productFilterOptions';

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
                    ->label('Naam')
                    ->searchable()
                    ->sortable(),
                ImageColumn::make('image')
                    ->label('Afbeelding')
                    ->getStateUsing(fn ($record) => $record->image ? (mediaHelper()->getSingleMedia($record->image)->url ?? '') : ''),
            ])
            ->filters([
                //
            ])
            ->reorderable('order')
            ->recordActions([
                Action::make('edit')
                    ->label('Bewerken')
                    ->icon('heroicon-o-pencil-square')
                    ->url(fn (ProductFilterOption $record) => route('filament.dashed.resources.product-filter-options.edit', [$record])),
                DeleteAction::make(),
            ])
            ->headerActions([
                Action::make('Aanmaken')
                    ->button()
                    ->url(fn ($livewire) => route('filament.dashed.resources.product-filter-options.create') . '?productFilterId=' . $livewire->ownerRecord->id),
            ]);
    }
}
