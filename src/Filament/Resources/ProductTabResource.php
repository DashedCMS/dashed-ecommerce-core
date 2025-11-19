<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources;

use UnitEnum;
use BackedEnum;
use Filament\Tables\Table;
use Filament\Actions\Action;
use Filament\Schemas\Schema;
use Filament\Actions\EditAction;
use Filament\Resources\Resource;
use Filament\Actions\DeleteAction;
use Illuminate\Support\Facades\DB;
use Filament\Forms\Components\Select;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Dashed\DashedEcommerceCore\Models\Product;
use Filament\Schemas\Components\Utilities\Set;
use Dashed\DashedEcommerceCore\Models\ProductTab;
use Dashed\DashedEcommerceCore\Models\ProductCategory;
use Dashed\DashedCore\Filament\Concerns\HasCustomBlocksTab;
use LaraZeus\SpatieTranslatable\Resources\Concerns\Translatable;
use Dashed\DashedCore\Classes\Actions\ActionGroups\ToolbarActions;
use Dashed\DashedEcommerceCore\Filament\Resources\ProductTabResource\Pages\EditProductTab;
use Dashed\DashedEcommerceCore\Filament\Resources\ProductTabResource\Pages\ListProductTab;
use Dashed\DashedEcommerceCore\Filament\Resources\ProductTabResource\Pages\CreateProductTab;

class ProductTabResource extends Resource
{
    use Translatable;
    use HasCustomBlocksTab;

    protected static ?string $model = ProductTab::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-plus';
    protected static string | UnitEnum | null $navigationGroup = 'Producten';
    protected static ?string $navigationLabel = 'Product tabs';
    protected static ?string $label = 'Product tab';
    protected static ?string $pluralLabel = 'Product tabs';
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
                        TextInput::make('name')
                            ->label('Naam')
                            ->required()
                            ->maxLength(100),
                        cms()->editorField('content', 'Content')
                            ->required(),
                        Select::make('products')
                            ->relationship('products', 'name')
                            ->label('Gekoppelde producten')
                            ->getSearchResultsUsing(fn (string $search) => Product::where(DB::raw('lower(name)'), 'like', '%' . strtolower($search) . '%')->limit(50)->pluck('name', 'id'))
                            ->searchable()
                            ->multiple()
                            ->getOptionLabelFromRecordUsing(fn ($record) => $record->nameWithParents)
                            ->hintAction(
                                Action::make('addAllProducts')
                                ->label('Voeg alle producten toe')
                                ->action(function (Set $set) {
                                    $set('products', Product::all()->pluck('id')->toArray());
                                }),
                            ),
                        Select::make('productCategories')
                            ->relationship('productCategories', 'name')
                            ->label('Gekoppelde categorieen')
                            ->getSearchResultsUsing(fn (string $search) => ProductCategory::where(DB::raw('lower(name)'), 'like', '%' . strtolower($search) . '%')->limit(50)->pluck('name', 'id'))
                            ->searchable()
                            ->multiple()
                            ->getOptionLabelFromRecordUsing(fn ($record) => $record->nameWithParents)
                            ->hintAction(
                                Action::make('addAllCategories')
                                    ->label('Voeg alle categorieen toe')
                                    ->action(function (Set $set) {
                                        $set('productCategories', ProductCategory::all()->pluck('id')->toArray());
                                    }),
                            ),
                    ])),
            ], static::customBlocksTab('productTabBlocks')));
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Naam')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('products_count')
                    ->counts('products')
                    ->label('Aantal producten')
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
            ->toolbarActions(ToolbarActions::getActions())
            ->query(ProductTab::query()->where('global', 1));
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProductTab::route('/'),
            'create' => CreateProductTab::route('/create'),
            'edit' => EditProductTab::route('/{record}/edit'),
        ];
    }
}
