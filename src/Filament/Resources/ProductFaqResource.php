<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources;

use Filament\Forms\Set;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Filament\Resources\Resource;
use Illuminate\Support\Facades\DB;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Repeater;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Forms\Components\Actions\Action;
use Filament\Resources\Concerns\Translatable;
use Filament\Tables\Actions\DeleteBulkAction;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Models\ProductFaq;
use Dashed\DashedEcommerceCore\Models\ProductCategory;
use Dashed\DashedCore\Filament\Concerns\HasCustomBlocksTab;
use Dashed\DashedEcommerceCore\Filament\Resources\ProductFaqResource\Pages\EditProductFaq;
use Dashed\DashedEcommerceCore\Filament\Resources\ProductFaqResource\Pages\ListProductFaq;
use Dashed\DashedEcommerceCore\Filament\Resources\ProductFaqResource\Pages\CreateProductFaq;

class ProductFaqResource extends Resource
{
    use Translatable;
    use HasCustomBlocksTab;

    protected static ?string $model = ProductFaq::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $navigationIcon = 'heroicon-o-question-mark-circle';
    protected static ?string $navigationGroup = 'Producten';
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
                        Repeater::make('questions')
                            ->label('Vragen')
                            ->schema([
                                TextInput::make('question')
                                    ->label('Vraag')
                                    ->required()
                                    ->maxLength(255),
                                cms()->editorField('answer')
                                    ->label('Antwoord')
                                    ->required()
                                    ->columnSpanFull(),
                            ])
                            ->columns(1)
                            ->defaultItems(1)
                            ->collapsible(),
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
            ], static::customBlocksTab('productFaqBlocks')));
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
