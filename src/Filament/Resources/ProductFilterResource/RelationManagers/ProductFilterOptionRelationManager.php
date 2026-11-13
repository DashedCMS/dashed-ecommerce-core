<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources\ProductFilterResource\RelationManagers;

use Filament\Tables\Table;
use Filament\Actions\Action;
use Filament\Schemas\Schema;
use Filament\Actions\DeleteAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Resources\RelationManagers\RelationManager;
use Dashed\DashedEcommerceCore\Models\ProductFilterOption;
use Dashed\DashedFiles\Filament\Tables\Columns\MediaImageColumn;

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
                    ->label(__('Naam'))
                    ->searchable()
                    ->sortable(),
                MediaImageColumn::make('image')
                    ->label(__('Afbeelding')),
                IconColumn::make('in_stock')
                    ->label(__('Op voorraad'))
                    ->trueIcon('heroicon-o-check-circle')
                    ->trueColor('success')
                    ->falseIcon('heroicon-o-x-circle')
                    ->falseColor('danger')
                    ->sortable()
                    ->visible(fn () => $this->ownerRecord->use_stock),
            ])
            ->filters([
                //
            ])
            ->reorderable('order')
            ->recordActions([
                Action::make('edit')
                    ->label(__('Bewerken'))
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
