<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources;

use App\Models\User;
use Dashed\DashedEcommerceCore\Models\ProductCategory;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Filament\Resources\Resource;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Section;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Actions\Action;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Classes\CurrencyHelper;
use Dashed\DashedCore\Filament\Concerns\HasCustomBlocksTab;
use Dashed\DashedEcommerceCore\Filament\Resources\ProductTabResource\Pages\CreateProductTab;
use Dashed\DashedEcommerceCore\Filament\Resources\PricePerUserResource\Pages\EditPricePerUser;
use Dashed\DashedEcommerceCore\Filament\Resources\PricePerUserResource\Pages\ListPricePerUser;

class PricePerUserResource extends Resource
{
    use HasCustomBlocksTab;

    protected static ?string $model = User::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';
    protected static ?string $navigationGroup = 'Gebruikers';
    protected static ?string $navigationLabel = 'Prijs per gebruiker';
    protected static ?string $label = 'Prijs per gebruiker';
    protected static ?string $pluralLabel = 'Prijs per gebruiker';
    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        $products = Product::all();
        $productCategories = ProductCategory::all();

        $schema = [
            Section::make()
                ->schema([
                    Placeholder::make('pricePerUser')
                        ->label('Prijs per gebruiker')
                        ->content('Vul hier een korting per product in voor de gebruiker, of doe het in bulk met de export/import functie. De categorieen overschrijven ALTIJD de producten, en met het verwijderen van een categorie uit de lijst worden de producten van die categorie ook verwijderd.'),
                ]),
        ];

        $productCategorySchema = [];

        foreach ($productCategories as $productCategory) {
            $productCategorySchema[] = Section::make($productCategory->name)
                ->schema([
                    TextInput::make($productCategory->id . '_category_discount_price')
                        ->label('Korting bedrag')
                        ->prefix('€')
                        ->required(fn(Get $get) => $get($productCategory->id . '_category_discount_percentage') === null)
                        ->minValue(1)
                        ->reactive()
                        ->numeric(),
                    TextInput::make($productCategory->id . '_category_discount_percentage')
                        ->label('Korting percentage')
                        ->suffix('%')
                        ->minValue(1)
                        ->maxValue(100)
                        ->nullable()
                        ->required(fn(Get $get) => $get($productCategory->id . '_category_discount_price') === null)
                        ->reactive()
                        ->numeric(),
                ])
                ->headerActions([
                    Action::make('delete')
                        ->label('Verwijder')
                        ->hiddenLabel()
                        ->icon('heroicon-o-trash')
                        ->color('danger')
                        ->action(function (Set $set, Get $get) use ($productCategory) {
                            $values = $get('product_category_ids');
                            $values = array_diff($values, [$productCategory->id]);
                            $set('product_category_ids', $values);
                        }),
                ])
                ->visible(fn(Get $get) => collect($get('product_category_ids'))->contains($productCategory->id))
                ->columns(2);
        }

        $schema[] = Section::make()
            ->schema(array_merge([
                Select::make('product_category_ids')
                    ->label('Product categorieen')
                    ->multiple()
                    ->options($productCategories->pluck('name', 'id')->toArray())
                    ->searchable()
                    ->reactive(),
            ], $productCategorySchema));

        $productSchema = [];

        foreach ($products as $product) {
            $productSchema[] = Section::make($product->name)
                ->schema([
                    TextInput::make($product->id . '_price')
                        ->label('Prijs')
                        ->prefix('€')
                        ->disabled(),
                    TextInput::make($product->id . '_discount_price')
                        ->label('Korting bedrag')
                        ->prefix('€')
                        ->helperText('Product prijs: ' . CurrencyHelper::formatPrice($product->getRawOriginal('current_price')))
                        ->required(fn(Get $get) => $get($product->id . '_discount_percentage') === null)
                        ->minValue(1)
                        ->maxValue($product->getRawOriginal('current_price') - 1)
                        ->reactive()
                        ->numeric(),
                    TextInput::make($product->id . '_discount_percentage')
                        ->label('Korting percentage')
                        ->suffix('%')
                        ->minValue(1)
                        ->maxValue(100)
                        ->nullable()
                        ->required(fn(Get $get) => $get($product->id . '_discount_price') === null)
                        ->reactive()
                        ->numeric(),
                ])
                ->headerActions([
                    Action::make('delete')
                        ->label('Verwijder')
                        ->hiddenLabel()
                        ->icon('heroicon-o-trash')
                        ->color('danger')
                        ->action(function (Set $set, Get $get) use ($product) {
                            $values = $get('product_ids');
                            $values = array_diff($values, [$product->id]);
                            $set('product_ids', $values);
                        }),
                ])
                ->visible(fn(Get $get) => collect($get('product_ids'))->contains($product->id))
                ->columns(3);
        }

        $schema[] = Section::make()
            ->schema(array_merge([
                Select::make('product_ids')
                    ->label('Product')
                    ->multiple()
                    ->options($products->pluck('name', 'id')->toArray())
                    ->searchable()
                    ->reactive(),
            ], $productSchema));

        return $form
            ->schema($schema);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Naam')
                    ->searchable()
                    ->sortable(),
            ])
            ->reorderable('order')
            ->filters([
                //
            ])
            ->actions([
                EditAction::make()
                    ->button(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPricePerUser::route('/'),
//            'create' => CreateProductTab::route('/create'),
            'edit' => EditPricePerUser::route('/{record}/edit'),
        ];
    }
}
