<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources;

use Dashed\DashedEcommerceCore\Models\ProductGroup;
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
use Dashed\DashedEcommerceCore\Models\Product;
use Filament\Schemas\Components\Utilities\Set;
use Dashed\DashedEcommerceCore\Models\ProductExtra;
use Dashed\DashedEcommerceCore\Models\ProductCategory;
use Dashed\DashedCore\Filament\Concerns\HasCustomBlocksTab;
use LaraZeus\SpatieTranslatable\Resources\Concerns\Translatable;
use Dashed\DashedCore\Classes\Actions\ActionGroups\ToolbarActions;
use Dashed\DashedEcommerceCore\Filament\Resources\ProductExtraResource\Pages\EditProductExtra;
use Dashed\DashedEcommerceCore\Filament\Resources\ProductExtraResource\Pages\ListProductExtra;
use Dashed\DashedEcommerceCore\Filament\Resources\ProductExtraResource\Pages\CreateProductExtra;

class ProductExtraResource extends Resource
{
    use Translatable;
    use HasCustomBlocksTab;

    protected static ?string $model = ProductExtra::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-beaker';
    protected static string | UnitEnum | null $navigationGroup = 'Producten';
    protected static ?string $navigationLabel = 'Product extras';
    protected static ?string $label = 'Product extra';
    protected static ?string $pluralLabel = 'Product extras';
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
            ->schema(array_merge(
                ProductExtra::getFilamentFields(),
                [
                    Select::make('productGroups')
                        ->relationship('productGroups', 'name')
                        ->label('Gekoppelde product groepen')
                        ->getSearchResultsUsing(fn (string $search) => ProductGroup::where(DB::raw('lower(name)'), 'like', '%' . strtolower($search) . '%')->limit(50)->pluck('name', 'id'))
                        ->searchable()
                        ->multiple()
                        ->getOptionLabelFromRecordUsing(fn ($record) => $record->nameWithParents)
                        ->hintAction(
                            Action::make('addAllProductGroups')
                                ->label('Voeg alle product groepen toe')
                                ->action(function (Set $set) {
                                    $set('productGroups', ProductGroup::all()->pluck('id')->toArray());
                                }),
                        ),
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
                ],
                static::customBlocksTab('productExtraOptionBlocks')
            ));
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
            ->query(ProductExtra::query()->where('global', 1));
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProductExtra::route('/'),
            'create' => CreateProductExtra::route('/create'),
            'edit' => EditProductExtra::route('/{record}/edit'),
        ];
    }
}
