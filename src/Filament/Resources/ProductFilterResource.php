<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources;

use UnitEnum;
use BackedEnum;
use Filament\Tables\Table;
use Filament\Schemas\Schema;
use Filament\Actions\EditAction;
use Filament\Resources\Resource;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Dashed\DashedEcommerceCore\Models\ProductFilter;
use Dashed\DashedCore\Classes\QueryHelpers\SearchQuery;
use Dashed\DashedCore\Filament\Concerns\HasCustomBlocksTab;
use LaraZeus\SpatieTranslatable\Resources\Concerns\Translatable;
use Dashed\DashedEcommerceCore\Filament\Resources\ProductFilterResource\Pages\EditProductFilter;
use Dashed\DashedEcommerceCore\Filament\Resources\ProductFilterResource\Pages\ListProductFilter;
use Dashed\DashedEcommerceCore\Filament\Resources\ProductFilterResource\Pages\CreateProductFilter;
use Dashed\DashedEcommerceCore\Filament\Resources\ProductFilterResource\RelationManagers\ProductFilterOptionRelationManager;

class ProductFilterResource extends Resource
{
    use Translatable;
    use HasCustomBlocksTab;

    protected static ?string $model = ProductFilter::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-shopping-bag';
    protected static string | UnitEnum | null $navigationGroup = 'Producten';
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

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema(array_merge([
                Section::make('Content')->columnSpanFull()
                    ->schema(array_merge([
                        Toggle::make('hide_filter_on_overview_page')
                            ->label('Moet deze filter verborgen worden op de overzichts pagina van de producten?'),
                        TextInput::make('name')
                            ->label('Naam')
                            ->required()
                            ->maxLength(100),
                        Select::make('type')
                            ->label('Type')
                            ->default('select')
                            ->options([
                                'select' => 'Dropdown',
                                'image' => 'Afbeelding',
                            ])
                            ->required(),
                    ])),
            ], static::customBlocksTab('productFilterBlocks')));
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
            ->reorderable('order')
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make()
                    ->button(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
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
