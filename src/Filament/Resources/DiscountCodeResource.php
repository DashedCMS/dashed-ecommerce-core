<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources;

use UnitEnum;
use BackedEnum;
use Filament\Tables\Table;
use Filament\Schemas\Schema;
use Filament\Actions\EditAction;
use Filament\Resources\Resource;
use Filament\Actions\DeleteAction;
use Dashed\DashedCore\Classes\Sites;
use Filament\Forms\Components\Radio;
use Filament\Actions\BulkActionGroup;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\DateTimePicker;
use Dashed\DashedEcommerceCore\Models\Product;
use Filament\Schemas\Components\Utilities\Get;
use Dashed\DashedEcommerceCore\Models\DiscountCode;
use Dashed\DashedEcommerceCore\Models\ProductCategory;
use Dashed\DashedCore\Classes\QueryHelpers\RelationshipSearchQuery;
use Dashed\DashedEcommerceCore\Filament\Resources\DiscountCodeResource\Pages\EditDiscountCode;
use Dashed\DashedEcommerceCore\Filament\Resources\DiscountCodeResource\Pages\ListDiscountCodes;
use Dashed\DashedEcommerceCore\Filament\Resources\DiscountCodeResource\Pages\CreateDiscountCode;

class DiscountCodeResource extends Resource
{
    use \Dashed\DashedCore\Filament\Concerns\HasLastEditedColumn;

    protected static ?string $model = DiscountCode::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-receipt-percent';
    protected static string | UnitEnum | null $navigationGroup = 'E-commerce';
    protected static ?string $navigationLabel = 'Kortingscodes';
    protected static ?string $label = 'Kortingscode';
    protected static ?string $pluralLabel = 'Kortingscodes';
    protected static ?int $navigationSort = 50;

    public static function getGloballySearchableAttributes(): array
    {
        return [
            'site_ids',
            'name',
            'code',
            'type',
            'start_date',
            'end_date',
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make(__('Content'))
                    ->columnSpanFull()
                    ->schema(
                        array_merge([
                            Toggle::make('is_global_discount')
                                ->label(__('Is globale korting'))
                                ->helperText(__('Als deze optie is aangevinkt, wordt de kortingscode automatisch toegepast en is er geen code nodig.'))
                                ->reactive()
                                ->columnSpanFull()
                                ->hidden(fn ($livewire) => ! $livewire instanceof CreateDiscountCode),
                            Select::make('site_ids')
                                ->multiple()
                                ->label(__('Actief op sites'))
                                ->options(collect(Sites::getSites())->pluck('name', 'id')->toArray())
                                ->hidden(function () {
                                    return ! (Sites::getAmountOfSites() > 1);
                                })
                                ->required(),
                            TextInput::make('name')
                                ->label(__('Naam'))
                                ->required()
                                ->maxLength(100),
                            TextInput::make('code')
                                ->label(__('Code'))
                                ->helperText(__('Deze code vullen mensen in om af te rekenen.'))
                                ->required()
                                ->unique('dashed__discount_codes', 'code', fn ($record) => $record)
                                ->hidden(fn (Get $get) => $get('is_global_discount'))
                                ->minLength(3)
                                ->maxLength(100),
                            Toggle::make('create_multiple_codes')
                                ->label(__('Meerdere codes aanmaken'))
                                ->reactive()
                                ->hidden(fn ($livewire, Get $get) => ! $livewire instanceof CreateDiscountCode || $get('is_global_discount')),
                            TextInput::make('amount_of_codes')
                                ->label(__('Hoeveel kortingscodes moeten er aangemaakt worden'))
                                ->helperText(__('Gebruik een * in de kortingscode om een willekeurige letter of getal neer te zetten. Gebruik er minstens 5! Voorbeeld: SITE*****ACTIE'))
                                ->type('number')
                                ->required()
                                ->maxValue(500)
                                ->hidden(fn (Get $get) => ! $get('create_multiple_codes') || $get('is_global_discount')),
                            Textarea::make('note')
                                ->label(__('Notitie'))
                                ->helperText(__('Notitie voor intern gebruik'))
                                ->maxLength(5000)
                                ->columnSpanFull(),
                        ])
                    )
                    ->columns(2),
                Section::make(__('Globale informatie'))
                    ->columnSpanFull()
                    ->schema([
                        DateTimePicker::make('start_date')
                            ->label(__('Vul een startdatum in voor de kortingscode'))
                            ->nullable()
                            ->date(),
                        DateTimePicker::make('end_date')
                            ->label(__('Vul een einddatum in voor de kortingscode'))
                            ->nullable()
                            ->date()
                            ->after(fn ($get) => $get('start_date') ? 'start_date' : null),
                    ]),
                Section::make(__('Informatie'))
                    ->columnSpanFull()
                    ->schema(array_merge([
                        Radio::make('type')
                            ->required()
                            ->reactive()
                            ->options([
                                'percentage' => __('Percentage'),
                                'amount' => __('Vast bedrag'),
                            ]),
                        TextInput::make('discount_percentage')
                            ->label(__('Kortingswaarde'))
                            ->helperText(__('Hoeveel procent korting krijg je met deze code'))
                            ->numeric()
                            ->prefix('%')
                            ->minValue(1)
                            ->maxValue(100)
                            ->required()
                            ->hidden(fn ($get) => $get('type') != 'percentage'),
                        TextInput::make('discount_amount')
                            ->label(__('Kortingswaarde'))
                            ->helperText(__('Hoeveel euro korting krijg je met deze code'))
                            ->prefix('€')
                            ->minValue(1)
                            ->maxValue(100000)
                            ->numeric()
                            ->required()
                            ->hidden(fn ($get) => $get('type') != 'amount'),
                        Radio::make('valid_for')
                            ->label(__('Van toepassing op'))
                            ->reactive()
                            ->options([
                                null => __('Alle producten'),
                                'products' => __('Specifieke producten'),
                                'categories' => __('Specifieke categorieën'),
                            ]),
                        Select::make('products')
                            ->relationship('products', 'name')
                            ->multiple()
                            ->getSearchResultsUsing(fn (string $search) => RelationshipSearchQuery::make(Product::class, $search))
                            ->getOptionLabelFromRecordUsing(fn ($record) => $record->nameWithParents)
                            ->label(__('Selecteer producten waar deze kortingscode voor geldt'))
                            ->required()
                            ->hidden(fn (Get $get) => $get('valid_for') != 'products'),
                        Select::make('productCategories')
                            ->relationship('productCategories', 'name')
                            ->multiple()
                            ->getSearchResultsUsing(fn (string $search) => RelationshipSearchQuery::make(ProductCategory::class, $search))
                            ->getOptionLabelFromRecordUsing(fn ($record) => $record->nameWithParents)
                            ->label(__('Selecteer categorieën waar deze kortingscode voor geldt'))
                            ->required(fn (Get $get) => $get('valid_for') == 'categories')
                            ->hidden(fn (Get $get) => $get('valid_for') != 'categories'),
                        Radio::make('minimal_requirements')
                            ->label(__('Minimale eisen'))
                            ->hidden(fn (Get $get) => $get('is_global_discount'))
                            ->reactive()
                            ->options([
                                null => __('Geen'),
                                'products' => __('Minimaal aantal producten'),
                                'amount' => __('Minimaal aankoopbedrag'),
                            ]),
                        TextInput::make('minimum_products_count')
                            ->label(__('Minimum aantal producten'))
                            ->type('number')
                            ->minValue(1)
                            ->maxValue(100000)
                            ->numeric()
                            ->required()
                            ->hidden(fn ($get) => $get('minimal_requirements') != 'products' || $get('is_global_discount')),
                        TextInput::make('minimum_amount')
                            ->label(__('Minimum aankoopbedrag'))
                            ->prefix('€')
                            ->minValue(1)
                            ->maxValue(100000)
                            ->required()
                            ->numeric()
                            ->hidden(fn (Get $get) => $get('minimal_requirements') != 'amount' || $get('is_global_discount')),
                        Toggle::make('use_stock')
                            ->label(__('Een limiet instellen voor het aantal gebruiken van deze kortingscode'))
                            ->hidden(fn (Get $get) => $get('is_global_discount'))
                            ->reactive(),
                        TextInput::make('stock')
                            ->label(__('Hoe vaak mag de kortingscode nog gebruikt worden'))
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100000)
                            ->visible(fn (Get $get) => $get('use_stock') && ! $get('is_global_discount')),
                        Toggle::make('limit_use_per_customer')
                            ->hidden(fn (Get $get) => $get('is_global_discount'))
                            ->label(__('Deze kortingscode mag 1x per klant gebruikt worden')),
                    ])),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->query(DiscountCode::isNotGiftcard())
            ->columns([
                TextColumn::make('name')
                    ->label(__('Naam'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('code')
                    ->label(__('Code'))
                    ->default('-')
                    ->searchable()
                    ->sortable(),
                IconColumn::make('is_global_discount')
                    ->label(__('Globale korting'))
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle'),
                TextColumn::make('site_ids')
                    ->label(__('Actief op site(s)'))
                    ->sortable()
                    ->badge()
                    ->hidden(! (Sites::getAmountOfSites() > 1))
                    ->searchable(),
                TextColumn::make('amountOfUses')
                    ->label(__('Aantal gebruiken'))
                    ->getStateUsing(function ($record) {
                        return "{$record->stock_used}x gebruikt / " . ($record->use_stock ? $record->stock . ' gebruiken over' : 'geen limiet');
                    }),
                TextColumn::make('status')
                    ->label(__('Status')),
                static::lastEditedColumn(),
            ])
            ->modifyQueryUsing(fn ($query) => static::modifyTableQueryForLastEdited($query))
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
            DiscountCodeResource\RelationManagers\OrdersRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDiscountCodes::route('/'),
            'create' => CreateDiscountCode::route('/create'),
            'edit' => EditDiscountCode::route('/{record}/edit'),
        ];
    }
}
