<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources;

use Filament\Forms\Set;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Filament\Resources\Resource;
use Illuminate\Support\Facades\DB;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Section;
use FilamentTiptapEditor\TiptapEditor;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Forms\Components\Actions\Action;
use Filament\Resources\Concerns\Translatable;
use Filament\Tables\Actions\DeleteBulkAction;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Models\ProductTab;
use Dashed\DashedCore\Filament\Concerns\HasCustomBlocksTab;
use Dashed\DashedEcommerceCore\Filament\Resources\ProductTabResource\Pages\EditProductTab;
use Dashed\DashedEcommerceCore\Filament\Resources\ProductTabResource\Pages\ListProductTab;
use Dashed\DashedEcommerceCore\Filament\Resources\ProductTabResource\Pages\CreateProductTab;

class ProductTabResource extends Resource
{
    use Translatable;
    use HasCustomBlocksTab;

    protected static ?string $model = ProductTab::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $navigationIcon = 'heroicon-o-plus';
    protected static ?string $navigationGroup = 'E-commerce';
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

    public static function form(Form $form): Form
    {
        return $form
            ->schema(array_merge([
                Section::make('Content')
                    ->schema(array_merge([
                        TextInput::make('name')
                            ->label('Naam')
                            ->required()
                            ->maxLength(100),
                        TiptapEditor::make('content')
                            ->label('Content')
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
            ->actions([
                EditAction::make()
                    ->button(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
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
