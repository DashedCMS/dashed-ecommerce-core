<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources;

use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Table;
use Filament\Resources\Resource;
use Illuminate\Support\Facades\DB;
use Dashed\DashedCore\Classes\Sites;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Forms\Components\DateTimePicker;
use Filament\Tables\Actions\DeleteBulkAction;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Models\DiscountCode;
use Dashed\DashedEcommerceCore\Models\ProductCategory;
use Dashed\DashedEcommerceCore\Filament\Resources\DiscountCodeResource\Pages\EditDiscountCode;
use Dashed\DashedEcommerceCore\Filament\Resources\DiscountCodeResource\Pages\ListDiscountCodes;
use Dashed\DashedEcommerceCore\Filament\Resources\DiscountCodeResource\Pages\CreateDiscountCode;

class DiscountCodeResource extends Resource
{
    protected static ?string $model = DiscountCode::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $navigationIcon = 'heroicon-o-receipt-percent';
    protected static ?string $navigationGroup = 'E-commerce';
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

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Content')
                    ->schema(
                        array_merge([
                            Toggle::make('is_global_discount')
                                ->label('Is globale korting')
                                ->helperText('Als deze optie is aangevinkt, wordt de kortingscode automatisch toegepast en is er geen code nodig.')
                                ->reactive()
                                ->columnSpanFull()
                                ->hidden(fn($livewire) => !$livewire instanceof CreateDiscountCode),
                            Select::make('site_ids')
                                ->multiple()
                                ->label('Actief op sites')
                                ->options(collect(Sites::getSites())->pluck('name', 'id')->toArray())
                                ->hidden(function () {
                                    return !(Sites::getAmountOfSites() > 1);
                                })
                                ->required(),
                            TextInput::make('name')
                                ->label('Name')
                                ->required()
                                ->maxLength(100),
                            TextInput::make('code')
                                ->label('Code')
                                ->helperText('Deze code vullen mensen in om af te rekenen.')
                                ->required()
                                ->unique('dashed__discount_codes', 'code', fn($record) => $record)
                                ->hidden(fn(Get $get) => $get('is_global_discount'))
                                ->minLength(3)
                                ->maxLength(100),
                            Toggle::make('create_multiple_codes')
                                ->label('Meerdere codes aanmaken')
                                ->reactive()
                                ->hidden(fn($livewire, Get $get) => !$livewire instanceof CreateDiscountCode || $get('is_global_discount')),
                            TextInput::make('amount_of_codes')
                                ->label('Hoeveel kortingscodes moeten er aangemaakt worden')
                                ->helperText('Gebruik een * in de kortingscode om een willekeurige letter of getal neer te zetten. Gebruik er minstens 5! Voorbeeld: SITE*****ACTIE')
                                ->type('number')
                                ->required()
                                ->maxValue(500)
                                ->hidden(fn(Get $get) => !$get('create_multiple_codes') || $get('is_global_discount')),
                            Textarea::make('note')
                                ->label('Notitie')
                                ->helperText('Notitie voor intern gebruik')
                                ->maxLength(5000)
                                ->columnSpanFull(),
                        ])
                    )
                    ->columns(2),
                Section::make('Globale informatie')
                    ->schema([
                        DateTimePicker::make('start_date')
                            ->label('Vul een startdatum in voor de kortingscode')
                            ->nullable()
                            ->date(),
                        DateTimePicker::make('end_date')
                            ->label('Vul een einddatum in voor de kortingscode')
                            ->nullable()
                            ->date()
                            ->after(fn($get) => $get('start_date') ? 'start_date' : null),
                    ]),
                Section::make('Informatie')
                    ->schema(array_merge([
                        Radio::make('type')
                            ->required()
                            ->reactive()
                            ->options([
                                'percentage' => 'Percentage',
                                'amount' => 'Vast bedrag',
                            ]),
                        TextInput::make('discount_percentage')
                            ->label('Kortingswaarde')
                            ->helperText('Hoeveel procent korting krijg je met deze code')
                            ->numeric()
                            ->prefix('%')
                            ->minValue(1)
                            ->maxValue(100)
                            ->required()
                            ->hidden(fn($get) => $get('type') != 'percentage'),
                        TextInput::make('discount_amount')
                            ->label('Kortingswaarde')
                            ->helperText('Hoeveel euro korting krijg je met deze code')
                            ->prefix('€')
                            ->minValue(1)
                            ->maxValue(100000)
                            ->numeric()
                            ->required()
                            ->hidden(fn($get) => $get('type') != 'amount'),
                        Radio::make('valid_for')
                            ->label('Van toepassing op')
                            ->reactive()
                            ->options([
                                null => 'Alle producten',
                                'products' => 'Specifieke producten',
                                'categories' => 'Specifieke categorieën',
                            ]),
                        Select::make('products')
                            ->relationship('products', 'name')
                            ->multiple()
                            ->getSearchResultsUsing(fn(string $search) => Product::where(DB::raw('lower(name)'), 'like', '%' . strtolower($search) . '%')->limit(50)->pluck('name', 'id'))
                            ->getOptionLabelFromRecordUsing(fn($record) => $record->nameWithParents)
                            ->label('Selecteer producten waar deze kortingscode voor geldt')
                            ->required()
                            ->hidden(fn(Get $get) => $get('valid_for') != 'products'),
                        Select::make('productCategories')
                            ->relationship('productCategories', 'name')
                            ->multiple()
                            ->getSearchResultsUsing(fn(string $search) => ProductCategory::where(DB::raw('lower(name)'), 'like', '%' . strtolower($search) . '%')->limit(50)->pluck('name', 'id'))
                            ->getOptionLabelFromRecordUsing(fn($record) => $record->nameWithParents)
                            ->label('Selecteer categorieën waar deze kortingscode voor geldt')
                            ->required(fn(Get $get) => $get('valid_for') == 'categories')
                            ->hidden(fn(Get $get) => $get('valid_for') != 'categories'),
                        Radio::make('minimal_requirements')
                            ->label('Minimale eisen')
                            ->hidden(fn(Get $get) => $get('is_global_discount'))
                            ->reactive()
                            ->options([
                                null => 'Geen',
                                'products' => 'Minimaal aantal producten',
                                'amount' => 'Minimaal aankoopbedrag',
                            ]),
                        TextInput::make('minimum_products_count')
                            ->label('Minimum aantal producten')
                            ->type('number')
                            ->minValue(1)
                            ->maxValue(100000)
                            ->numeric()
                            ->required()
                            ->hidden(fn($get) => $get('minimal_requirements') != 'products' || $get('is_global_discount')),
                        TextInput::make('minimum_amount')
                            ->label('Minimum aankoopbedrag')
                            ->prefix('€')
                            ->minValue(1)
                            ->maxValue(100000)
                            ->required()
                            ->numeric()
                            ->hidden(fn(Get $get) => $get('minimal_requirements') != 'amount' || $get('is_global_discount')),
                        Toggle::make('use_stock')
                            ->label('Een limiet instellen voor het aantal gebruiken van deze kortingscode')
                            ->hidden(fn(Get $get) => $get('is_global_discount'))
                            ->reactive(),
                        TextInput::make('stock')
                            ->label('Hoe vaak mag de kortingscode nog gebruikt worden')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100000)
                            ->visible(fn(Get $get) => $get('use_stock') && !$get('is_global_discount')),
                        Toggle::make('limit_use_per_customer')
                            ->hidden(fn(Get $get) => $get('is_global_discount'))
                            ->label('Deze kortingscode mag 1x per klant gebruikt worden'),
                    ])),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Naam')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('code')
                    ->label('Code')
                    ->default('-')
                    ->searchable()
                    ->sortable(),
                IconColumn::make('is_global_discount')
                    ->label('Globale korting')
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle'),
                TextColumn::make('site_ids')
                    ->label('Actief op site(s)')
                    ->sortable()
                    ->badge()
                    ->hidden(!(Sites::getAmountOfSites() > 1))
                    ->searchable(),
                TextColumn::make('amountOfUses')
                    ->label('Aantal gebruiken')
                    ->getStateUsing(function ($record) {
                        return "{$record->stock_used}x gebruikt / " . ($record->use_stock ? $record->stock . ' gebruiken over' : 'geen limiet');
                    }),
                TextColumn::make('status')
                    ->label('Status'),
            ])
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
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
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
