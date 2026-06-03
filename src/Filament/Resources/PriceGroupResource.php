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
use Filament\Forms\Components\Toggle;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Dashed\DashedEcommerceCore\Models\PriceGroup;
use Dashed\DashedEcommerceCore\Models\ProductExtra;
use Dashed\DashedEcommerceCore\Models\ProductCategory;
use Dashed\DashedEcommerceCore\Filament\Resources\PriceGroupResource\Pages\EditPriceGroup;
use Dashed\DashedEcommerceCore\Filament\Resources\PriceGroupResource\Pages\ListPriceGroups;
use Dashed\DashedEcommerceCore\Filament\Resources\PriceGroupResource\Pages\CreatePriceGroup;

class PriceGroupResource extends Resource
{
    protected static ?string $model = PriceGroup::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-user-group';

    protected static string|UnitEnum|null $navigationGroup = 'Gebruikers';

    protected static ?string $navigationLabel = 'Prijsgroepen';

    protected static ?string $label = 'Prijsgroep';

    protected static ?string $pluralLabel = 'Prijsgroepen';

    protected static ?int $navigationSort = 4;

    public static function form(Schema $schema): Schema
    {
        $productCategories = ProductCategory::all();
        $productExtras = ProductExtra::with('productExtraOptions')->get();

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
                    Action::make('delete_category_' . $productCategory->id)
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

        $extraSchema = [];

        foreach ($productExtras as $productExtra) {
            $optionSections = [];

            foreach ($productExtra->productExtraOptions as $option) {
                $optionSections[] = Section::make((string) $option->value)
                    ->schema([
                        TextInput::make('extra_option_' . $option->id . '_price')
                            ->label('Vaste prijs')
                            ->prefix('€')
                            ->numeric()
                            ->nullable()
                            ->helperText('Standaard: € ' . number_format((float) $option->price, 2, ',', '.')),
                        TextInput::make('extra_option_' . $option->id . '_discount_percentage')
                            ->label('Korting percentage')
                            ->suffix('%')
                            ->minValue(1)
                            ->maxValue(100)
                            ->numeric()
                            ->nullable(),
                    ])
                    ->columns(2);
            }

            $parentFields = [
                TextInput::make('extra_' . $productExtra->id . '_price')
                    ->label('Vaste prijs voor deze extra')
                    ->prefix('€')
                    ->numeric()
                    ->nullable()
                    ->helperText('Standaard: € ' . number_format((float) $productExtra->price, 2, ',', '.')),
                TextInput::make('extra_' . $productExtra->id . '_discount_percentage')
                    ->label('Korting percentage')
                    ->suffix('%')
                    ->minValue(1)
                    ->maxValue(100)
                    ->numeric()
                    ->nullable(),
            ];

            if (! empty($optionSections) || (float) $productExtra->price) {
                $extraSchema[] = Section::make('Extra: ' . $productExtra->name)
                    ->schema(array_merge($parentFields, $optionSections))
                    ->columnSpanFull()
                    ->collapsed();
            }
        }

        $categoriesSection = Section::make()->columnSpanFull()
            ->schema(array_merge([
                Select::make('product_category_ids')
                    ->label('Product categorieen')
                    ->multiple()
                    ->options($productCategories->pluck('name', 'id')->toArray())
                    ->searchable()
                    ->reactive(),
            ], $productCategorySchema));

        $schemaComponents = [
            TextInput::make('name')
                ->label('Naam')
                ->required(),
            Toggle::make('show_prices_ex_vat')
                ->label('Toon prijzen ex BTW')
                ->helperText('Iedereen in deze groep ziet prijzen ex BTW.')
                ->default(false),
            $categoriesSection,
        ];

        foreach ($extraSchema as $section) {
            $schemaComponents[] = $section;
        }

        return $schema->schema($schemaComponents);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Naam')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('users_count')
                    ->counts('users')
                    ->label('Aantal gebruikers'),
                TextColumn::make('show_prices_ex_vat')
                    ->label('Prijzen ex BTW')
                    ->formatStateUsing(fn ($state) => $state ? 'Ja' : 'Nee'),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make()
                    ->button(),
                DeleteAction::make()
                    ->button(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPriceGroups::route('/'),
            'create' => CreatePriceGroup::route('/create'),
            'edit' => EditPriceGroup::route('/{record}/edit'),
        ];
    }
}
