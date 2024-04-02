<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources\ProductFilterResource\RelationManagers;

use Filament\Forms\Form;
use Filament\Tables\Table;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Actions\DeleteAction;
use Filament\Resources\RelationManagers\RelationManager;
use Dashed\DashedEcommerceCore\Models\ProductFilterOption;

class ProductFilterOptionRelationManager extends RelationManager
{
    protected static string $relationship = 'productFilterOptions';

    protected static ?string $recordTitleAttribute = 'name';

    public function form(Form $form): Form
    {
        return $form
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
            ])
            ->filters([
                //
            ])
            ->reorderable('order')
            ->actions([
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
