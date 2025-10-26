<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources\ProductFilterResource\RelationManagers;

use Filament\Tables\Table;
use Filament\Actions\Action;
use Filament\Schemas\Schema;
use Filament\Actions\DeleteAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Resources\RelationManagers\RelationManager;
use Dashed\DashedEcommerceCore\Models\ProductFilterOption;
use RalphJSmit\Filament\MediaLibrary\Filament\Tables\Columns\MediaColumn;

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
                MediaColumn::make('image')
                    ->label('Afbeelding'),
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
