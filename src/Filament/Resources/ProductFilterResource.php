<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources;

use Filament\Forms\Form;
use Filament\Tables\Table;
use Filament\Resources\Resource;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Section;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Resources\Concerns\Translatable;
use Filament\Tables\Actions\DeleteBulkAction;
use Dashed\DashedEcommerceCore\Models\ProductFilter;
use Dashed\DashedCore\Classes\QueryHelpers\SearchQuery;
use Dashed\DashedEcommerceCore\Filament\Resources\ProductFilterResource\Pages\EditProductFilter;
use Dashed\DashedEcommerceCore\Filament\Resources\ProductFilterResource\Pages\ListProductFilter;
use Dashed\DashedEcommerceCore\Filament\Resources\ProductFilterResource\Pages\CreateProductFilter;
use Dashed\DashedEcommerceCore\Filament\Resources\ProductFilterResource\RelationManagers\ProductFilterOptionRelationManager;

class ProductFilterResource extends Resource
{
    use Translatable;

    protected static ?string $model = ProductFilter::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';
    protected static ?string $navigationGroup = 'E-commerce';
    protected static ?string $navigationLabel = 'Product filters';
    protected static ?string $label = 'Product filter';
    protected static ?string $pluralLabel = 'Product filters';
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
                Section::make('Content')
                    ->schema(array_merge([
                        Toggle::make('hide_filter_on_overview_page')
                            ->label('Moet deze filter verborgen worden op de overzichts pagina van de producten?'),
                        TextInput::make('name')
                            ->label('Naam')
                            ->required()
                            ->maxLength(100),
                    ])),
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
                IconColumn::make('hide_filter_on_overview_page')
                    ->label('Tonen op website')
                    ->trueIcon('heroicon-o-eye-slash')
                    ->trueColor('danger')
                    ->falseIcon('heroicon-o-eye')
                    ->falseColor('success')
                    ->sortable(),
                TextColumn::make('product_filter_options_count')
                    ->counts('productFilterOptions')
                    ->label('Aantal waardes')
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                EditAction::make()
                    ->button(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            ProductFilterOptionRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProductFilter::route('/'),
            'create' => CreateProductFilter::route('/create'),
            'edit' => EditProductFilter::route('/{record}/edit'),
        ];
    }
}
