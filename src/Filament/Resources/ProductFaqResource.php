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
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Repeater;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Dashed\DashedEcommerceCore\Models\Product;
use Filament\Schemas\Components\Utilities\Set;
use Dashed\DashedEcommerceCore\Models\ProductFaq;
use Dashed\DashedEcommerceCore\Models\ProductCategory;
use Dashed\DashedCore\Filament\Concerns\HasCustomBlocksTab;
use LaraZeus\SpatieTranslatable\Resources\Concerns\Translatable;
use Dashed\DashedCore\Classes\Actions\ActionGroups\ToolbarActions;
use Dashed\DashedCore\Classes\QueryHelpers\RelationshipSearchQuery;
use Dashed\DashedEcommerceCore\Filament\Resources\ProductFaqResource\Pages\EditProductFaq;
use Dashed\DashedEcommerceCore\Filament\Resources\ProductFaqResource\Pages\ListProductFaq;
use Dashed\DashedEcommerceCore\Filament\Resources\ProductFaqResource\Pages\CreateProductFaq;

class ProductFaqResource extends Resource
{
    use Translatable;
    use HasCustomBlocksTab;

    protected static ?string $model = ProductFaq::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-question-mark-circle';
    protected static string | UnitEnum | null $navigationGroup = 'Producten';
    protected static ?string $navigationLabel = 'Product faqs';
    protected static ?string $label = 'Product faq';
    protected static ?string $pluralLabel = 'Product faqs';
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
                Section::make(__('Content'))
                    ->columnSpanFull()
                    ->schema(array_merge([
                        TextInput::make('name')
                            ->label(__('Naam'))
                            ->required()
                            ->maxLength(100),
                        Repeater::make('questions')
                            ->label(__('Vragen'))
                            ->schema([
                                TextInput::make('question')
                                    ->label(__('Vraag'))
                                    ->required()
                                    ->maxLength(255),
                                cms()->editorField('answer')
                                    ->label(__('Antwoord'))
                                    ->required()
                                    ->columnSpanFull(),
                            ])
                            ->columns(1)
                            ->defaultItems(1)
                            ->collapsible(),
                        Select::make('products')
                            ->relationship('products', 'name')
                            ->label(__('Gekoppelde producten'))
                            ->getSearchResultsUsing(fn (string $search) => RelationshipSearchQuery::make(Product::class, $search))
                            ->searchable()
                            ->multiple()
                            ->getOptionLabelFromRecordUsing(fn ($record) => $record->nameWithParents)
                            ->hintAction(
                                Action::make('addAllProducts')
                                    ->label(__('Voeg alle producten toe'))
                                    ->action(function (Set $set) {
                                        $set('products', Product::all()->pluck('id')->toArray());
                                    }),
                            ),
                        Select::make('productCategories')
                            ->relationship('productCategories', 'name')
                            ->label(__('Gekoppelde categorieen'))
                            ->getSearchResultsUsing(fn (string $search) => RelationshipSearchQuery::make(ProductCategory::class, $search))
                            ->searchable()
                            ->multiple()
                            ->getOptionLabelFromRecordUsing(fn ($record) => $record->nameWithParents)
                            ->hintAction(
                                Action::make('addAllCategories')
                                    ->label(__('Voeg alle categorieen toe'))
                                    ->action(function (Set $set) {
                                        $set('productCategories', ProductCategory::all()->pluck('id')->toArray());
                                    }),
                            ),
                    ])),
            ], static::customBlocksTab('productFaqBlocks')));
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('Naam'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('products_count')
                    ->counts('products')
                    ->label(__('Aantal producten'))
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
            ->query(ProductFaq::query()->where('global', 1));
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProductFaq::route('/'),
            'create' => CreateProductFaq::route('/create'),
            'edit' => EditProductFaq::route('/{record}/edit'),
        ];
    }
}
