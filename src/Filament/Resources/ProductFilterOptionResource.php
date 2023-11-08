<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources;

use Filament\Forms\Form;
use Filament\Tables\Table;
use Filament\Resources\Resource;
use Filament\Forms\Components\Select;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Actions\DeleteAction;
use Filament\Resources\Concerns\Translatable;
use Dashed\DashedCore\Classes\QueryHelpers\SearchQuery;
use Dashed\DashedEcommerceCore\Models\ProductFilterOption;
use Dashed\DashedEcommerceCore\Filament\Resources\ProductFilterOptionResource\Pages\EditProductFilterOption;
use Dashed\DashedEcommerceCore\Filament\Resources\ProductFilterOptionResource\Pages\ListProductFilterOption;
use Dashed\DashedEcommerceCore\Filament\Resources\ProductFilterOptionResource\Pages\CreateProductFilterOption;

class ProductFilterOptionResource extends Resource
{
    use Translatable;

    protected static ?string $model = ProductFilterOption::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static bool $shouldRegisterNavigation = false;
    protected static ?string $label = 'Product filter optie';
    protected static ?string $pluralLabel = 'Product filter opties';
    protected static ?int $navigationSort = 3;

    public static function getGloballySearchableAttributes(): array
    {
        return [
            'name',
        ];
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('product_filter_id')
                    ->relationship('productFilter', 'name')
                    ->label('Filter')
                    ->required()
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->name),
                TextInput::make('name')
                    ->label('Naam')
                    ->required()
                    ->maxLength(100),
                TextInput::make('order')
                    ->label('Volgorde')
                    ->required()
                    ->minValue(1)
                    ->maxValue(10000)
                    ->numeric()
                    ->default(1),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Naam')
                    ->searchable(query: SearchQuery::make())
                    ->sortable(),
                TextColumn::make('productFilter.name')
                    ->label('Filter')
                    ->sortable()
                    ->searchable(),
            ])
            ->actions([
                EditAction::make()
                    ->button(),
                DeleteAction::make(),
            ])
            ->filters([
                //
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProductFilterOption::route('/'),
            'create' => CreateProductFilterOption::route('/create'),
            'edit' => EditProductFilterOption::route('/{record}/edit'),
        ];
    }
}
