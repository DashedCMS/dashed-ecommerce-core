<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources;

use UnitEnum;
use BackedEnum;
use App\Models\User;
use Filament\Tables\Table;
use Filament\Actions\Action;
use Filament\Schemas\Schema;
use Filament\Actions\EditAction;
use Filament\Resources\Resource;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Dashed\DashedEcommerceCore\Models\Product;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Dashed\DashedEcommerceCore\Classes\CurrencyHelper;
use Dashed\DashedEcommerceCore\Models\ProductCategory;
use Dashed\DashedCore\Filament\Concerns\HasCustomBlocksTab;
use Dashed\DashedEcommerceCore\Filament\Resources\ProductTabResource\Pages\CreateProductTab;
use Dashed\DashedEcommerceCore\Filament\Resources\PricePerUserResource\Pages\EditPricePerUser;
use Dashed\DashedEcommerceCore\Filament\Resources\PricePerUserResource\Pages\ListPricePerUser;

class PricePerUserResource extends Resource
{
    use HasCustomBlocksTab;

    protected static ?string $model = User::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-banknotes';
    protected static string | UnitEnum | null $navigationGroup = 'Gebruikers';
    protected static ?string $navigationLabel = 'Prijs per gebruiker';
    protected static ?string $label = 'Prijs per gebruiker';
    protected static ?string $pluralLabel = 'Prijs per gebruiker';
    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        //        $products = Product::all();
        $productCategories = ProductCategory::all();

        $newSchema = [
            Section::make()->columnSpanFull()
                ->schema([
                    TextEntry::make('pricePerUser')
                        ->state('Prijs per gebruiker')
                        ->state('Vul hier een korting per product in voor de gebruiker, of doe het in bulk met de export/import functie. De categorieen overschrijven ALTIJD de producten, en met het verwijderen van een categorie uit de lijst worden de producten van die categorie ook verwijderd. Na het opslaan moet je even wachten tot het verwerkt is, refresh na 30 seconden om verdere aanpassingen door te voeren. Producten zijn alleen maar via de import aan te passen.'),
                ]),
        ];

        $productCategorySchema = [];

        foreach ($productCategories as $productCategory) {
            $productCategorySchema[] = Section::make($productCategory->name)->columnSpanFull()
                ->schema([
                    TextInput::make($productCategory->id . '_category_discount_price')
                        ->label('Korting bedrag')
                        ->prefix('€')
                        ->required(fn (Get $get) => $get($productCategory->id . '_category_discount_percentage') === null)
                        ->minValue(1)
                        ->reactive()
                        ->numeric(),
                    TextInput::make($productCategory->id . '_category_discount_percentage')
                        ->label('Korting percentage')
                        ->suffix('%')
                        ->minValue(1)
                        ->maxValue(100)
                        ->nullable()
                        ->required(fn (Get $get) => $get($productCategory->id . '_category_discount_price') === null)
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
                ->visible(fn (Get $get) => collect($get('product_category_ids'))->contains($productCategory->id))
                ->columns(2);
        }

        $newSchema[] = Section::make()->columnSpanFull()
            ->schema(array_merge([
                Select::make('product_category_ids')
                    ->label('Product categorieen')
                    ->multiple()
                    ->options($productCategories->pluck('name', 'id')->toArray())
                    ->searchable()
                    ->reactive(),
            ], $productCategorySchema));

        $productSchema = [];

        //        foreach ($products as $product) {
        //            $productSchema[] = Section::make($product->name)->columnSpanFull()
        //                ->schema([
        //                    TextInput::make($product->id . '_price')
        //                        ->label('Prijs')
        //                        ->prefix('€')
        //                        ->disabled(),
        //                    TextInput::make($product->id . '_discount_price')
        //                        ->label('Korting bedrag')
        //                        ->prefix('€')
        //                        ->helperText('Product prijs: ' . CurrencyHelper::formatPrice($product->getRawOriginal('current_price')))
        //                        ->required(fn (Get $get) => $get($product->id . '_discount_percentage') === null)
        //                        ->minValue(1)
        //                        ->maxValue($product->getRawOriginal('current_price') - 1)
        //                        ->reactive()
        //                        ->numeric(),
        //                    TextInput::make($product->id . '_discount_percentage')
        //                        ->label('Korting percentage')
        //                        ->suffix('%')
        //                        ->minValue(1)
        //                        ->maxValue(100)
        //                        ->nullable()
        //                        ->required(fn (Get $get) => $get($product->id . '_discount_price') === null)
        //                        ->reactive()
        //                        ->numeric(),
        //                ])
        //                ->headerActions([
        //                    Action::make('delete')
        //                        ->label('Verwijder')
        //                        ->hiddenLabel()
        //                        ->icon('heroicon-o-trash')
        //                        ->color('danger')
        //                        ->action(function (Set $set, Get $get) use ($product) {
        //                            $values = $get('product_ids');
        //                            $values = array_diff($values, [$product->id]);
        //                            $set('product_ids', $values);
        //                        }),
        //                ])
        //                ->visible(fn (Get $get) => collect($get('product_ids'))->contains($product->id))
        //                ->columns(3);
        //        }
        //
        //        $schema[] = Section::make()->columnSpanFull()
        //            ->schema(array_merge([
        //                Select::make('product_ids')
        //                    ->label('Product')
        //                    ->multiple()
        //                    ->options($products->pluck('name', 'id')->toArray())
        //                    ->searchable()
        //                    ->reactive(),
        //            ], $productSchema));

        return $schema
            ->schema(array_merge([
                Toggle::make('has_custom_pricing')
                    ->label('Custom pricing voor deze gebruiker activeren'),
            ], $newSchema));
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
            ->recordActions([
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
