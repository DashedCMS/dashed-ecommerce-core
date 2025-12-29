<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources;

use Filament\Forms\Components\Toggle;
use Filament\Tables\Table;
use Filament\Schemas\Schema;
use Filament\Actions\EditAction;
use Filament\Resources\Resource;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Select;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Dashed\DashedEcommerceCore\Models\ProductFilter;
use Dashed\DashedCore\Classes\QueryHelpers\SearchQuery;
use Dashed\DashedEcommerceCore\Models\ProductFilterOption;
use LaraZeus\SpatieTranslatable\Resources\Concerns\Translatable;
use Dashed\DashedCore\Classes\Actions\ActionGroups\ToolbarActions;
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

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Select::make('product_filter_id')
                    ->relationship('productFilter', 'name')
                    ->default(request()->get('productFilterId'))
                    ->label('Filter')
                    ->required()
                    ->reactive()
                    ->getOptionLabelFromRecordUsing(fn($record) => $record->name),
                TextInput::make('name')
                    ->label('Naam')
                    ->required()
                    ->maxLength(100),
                Toggle::make('in_stock')
                    ->label('Op voorraad')
                    ->columnSpanFull()
                    ->visible(fn($record) => $record && $record->productFilter->use_stock)
                    ->default(true),
                mediaHelper()->field('image', 'Afbeelding')
                    ->required()
                    ->visible(fn(Get $get) => $get('product_filter_id') && ProductFilter::find($get('product_filter_id'))->type == 'image'),
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
            ->toolbarActions(ToolbarActions::getActions())
            ->recordActions([
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
