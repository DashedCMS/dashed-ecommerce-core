<?php

namespace Qubiqx\QcommerceEcommerceCore\Filament\Resources;

use Filament\Forms\Components\DatePicker;
use Filament\Resources\Form;
use Filament\Resources\Table;
use Filament\Resources\Resource;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Section;
use Filament\Tables\Columns\TagsColumn;
use Filament\Tables\Columns\TextColumn;
use Qubiqx\QcommerceCore\Classes\Sites;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\MultiSelect;
use Qubiqx\QcommerceEcommerceCore\Models\DiscountCode;
use Filament\Forms\Components\BelongsToManyMultiSelect;
use Qubiqx\QcommerceEcommerceCore\Filament\Resources\DiscountCodeResource\Pages\EditDiscountCode;
use Qubiqx\QcommerceEcommerceCore\Filament\Resources\DiscountCodeResource\Pages\ListDiscountCodes;
use Qubiqx\QcommerceEcommerceCore\Filament\Resources\DiscountCodeResource\Pages\CreateDiscountCode;

class DiscountCodeResource extends Resource
{
    protected static ?string $model = DiscountCode::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $navigationIcon = 'heroicon-o-receipt-tax';
    protected static ?string $navigationGroup = 'E-commerce';
    protected static ?string $navigationLabel = 'Kortingscodes';
    protected static ?string $label = 'Kortingscode';
    protected static ?string $pluralLabel = 'Kortingscodes';

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
                    ->schema([
                        MultiSelect::make('site_ids')
                            ->label('Actief op sites')
                            ->options(collect(Sites::getSites())->pluck('name', 'id'))
                            ->hidden(function () {
                                return !(Sites::getAmountOfSites() > 1);
                            })
                            ->required(),
                        TextInput::make('name')
                            ->label('Name')
                            ->required()
                            ->maxLength(100)
                            ->rules([
                                'max:100',
                            ]),
                        TextInput::make('code')
                            ->label('Code')
                            ->helperText('Deze code vullen mensen in om af te rekenen.')
                            ->required()
                            ->minLength(3)
                            ->maxLength(100)
                            ->rules([
                                'min:3',
                                'max:100',
                            ]),
                        Toggle::make('create_multiple_codes')
                            ->label('Meerdere codes aanmaken')
                            ->reactive()
                            ->hidden(fn($livewire) => !$livewire instanceof CreateDiscountCode),
                        TextInput::make('amount_of_codes')
                            ->label('Hoeveel kortingscodes moeten er aangemaakt worden')
                            ->helperText('Gebruik een * in de kortingscode om een willekeurige letter of getal neer te zetten. Gebruik er minstens 5! Voorbeeld: SITE*****ACTIE')
                            ->type('number')
                            ->maxValue(500)
                            ->hidden(fn($get) => !$get('create_multiple_codes')),
                    ])
                    ->collapsed(fn($livewire) => $livewire instanceof EditDiscountCode),
                Section::make('Informatie')
                    ->schema([
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
                            ->type('number')
                            ->prefix('%')
                            ->minValue(1)
                            ->maxValue(100)
                            ->required()
                            ->rules([
                                'required',
                                'numeric',
                                'min:1',
                                'max:100',
                            ])
                            ->hidden(fn($get) => $get('type') != 'percentage'),
                        TextInput::make('discount_amount')
                            ->label('Kortingswaarde')
                            ->helperText('Hoeveel euro korting krijg je met deze code')
                            ->prefix('€')
                            ->minValue(1)
                            ->maxValue(100000)
                            ->required()
                            ->rules([
                                'required',
                                'numeric',
                                'min:1',
                                'max:100000',
                            ])
                            ->hidden(fn($get) => $get('type') != 'amount'),
                        Radio::make('valid_for')
                            ->label('Van toepassing op')
                            ->reactive()
                            ->options([
                                '' => 'Alle producten',
                                'products' => 'Specifieke producten',
                                'categories' => 'Specifieke categorieën',
                            ]),
                        BelongsToManyMultiSelect::make('products')
                            ->relationship('products', 'name')
                            ->preload()
                            ->label('Selecteer producten waar deze kortingscode voor geldt, alleen als "Van toepassing op" gelijk is aan "Specifieke producten"')
                            ->required(fn($get) => $get('valid_for') == 'products')
                            ->rules([
//                                'required',
                            ]),
//                            ->hidden(fn($get) => $get('valid_for') != 'products'),
                        BelongsToManyMultiSelect::make('productCategories')
                            ->relationship('productCategories', 'name')
                            ->preload()
                            ->label('Selecteer categorieën waar deze kortingscode voor geldt, alleen als "Van toepassing op" gelijk is aan "Specifieke categorieën"')
                            ->required(fn($get) => $get('valid_for') == 'categories')
                            ->rules([
//                                'required',
                            ]),
//                            ->hidden(fn($get) => $get('valid_for') != 'categories'),
                        Radio::make('minimal_requirements')
                            ->label('Minimale eisen')
                            ->reactive()
                            ->options([
                                '' => 'Geen',
                                'products' => 'Minimaal aantal producten',
                                'amount' => 'Minimaal aankoopbedrag',
                            ]),
                        TextInput::make('minimum_products_count')
                            ->label('Minimum aantal producten')
                            ->type('number')
                            ->minValue(1)
                            ->maxValue(100000)
                            ->required()
                            ->rules([
                                'required',
                                'numeric',
                                'min:1',
                                'max:100000',
                            ])
                            ->hidden(fn($get) => $get('minimal_requirements') != 'products'),
                        TextInput::make('minimum_amount')
                            ->label('Minimum aankoopbedrag')
                            ->prefix('€')
                            ->minValue(1)
                            ->maxValue(100000)
                            ->required()
                            ->rules([
                                'required',
                                'numeric',
                                'min:1',
                                'max:100000',
                            ])
                            ->hidden(fn($get) => $get('minimal_requirements') != 'amount'),
                        Toggle::make('use_stock')
                            ->label('Een limiet instellen voor het aantal gebruiken van deze kortingscode')
                            ->reactive(),
                        TextInput::make('stock')
                            ->label('Hoe vaak mag de kortingscode nog gebruikt worden')
                            ->type('number')
                            ->minValue(0)
                            ->maxValue(100000)
                            ->required()
                            ->rules([
                                'required',
                                'numeric',
                                'min:0',
                                'max:100000',
                            ])
                            ->hidden(fn($get) => !$get('use_stock')),
                        Toggle::make('limit_use_per_customer')
                            ->label('Deze kortingscode mag 1x per klant gebruikt worden'),
                        DatePicker::make('start_date')
                            ->label('Vul een startdatum in voor de kortingscode')
                            ->rules([
                                'nullable',
                                'date'
                            ]),
                        DatePicker::make('end_date')
                            ->label('Vul een einddatum in voor de kortingscode')
                            ->rules([
                                'nullable',
                                'date',
                                'after:startDate',
                            ]),
                    ]),
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
                    ->searchable()
                    ->sortable(),
                TagsColumn::make('site_ids')
                    ->label('Actief op site(s)')
                    ->sortable()
                    ->hidden(!(Sites::getAmountOfSites() > 1))
                    ->searchable(),
                TextColumn::make('amount_of_uses')
                    ->label('Aantal gebruiken')
                    ->formatStateUsing(function ($record) {
                        return "{$record->stock_used}x gebruikt / " . ($record->use_stock ? $record->stock . ' gebruiken over' : 'geen limiet');
                    }),
                TextColumn::make('status')
                    ->label('Status'),
            ])
            ->filters([
                //
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
