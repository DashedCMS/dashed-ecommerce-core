<?php

namespace Qubiqx\QcommerceEcommerceCore\Filament\Resources;

use Filament\Resources\Form;
use Filament\Resources\Table;
use Filament\Resources\Resource;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\TextColumn;
use Qubiqx\QcommerceCore\Classes\Sites;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Concerns\Translatable;
use Qubiqx\QcommerceEcommerceCore\Filament\Resources\DiscountCodeResource\Pages\CreateDiscountCode;
use Qubiqx\QcommerceEcommerceCore\Filament\Resources\DiscountCodeResource\Pages\EditDiscountCode;
use Qubiqx\QcommerceEcommerceCore\Filament\Resources\DiscountCodeResource\Pages\ListDiscountCodes;
use Qubiqx\QcommerceEcommerceCore\Models\DiscountCode;

class DiscountCodeResource extends Resource
{
    use Translatable;

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
                Section::make('Globale informatie')
                    ->schema([
                        Select::make('site_id')
                            ->label('Actief op site')
                            ->options(collect(Sites::getSites())->pluck('name', 'id'))
                            ->hidden(function () {
                                return ! (Sites::getAmountOfSites() > 1);
                            })
                            ->required(),
                    ])
                    ->collapsed(fn ($livewire) => $livewire instanceof EditDiscountCode),
                Section::make('Content')
                    ->schema([
                        TextInput::make('name')
                            ->label('Name')
                            ->required()
                            ->maxLength(100)
                            ->rules([
                                'max:100',
                            ]),
                        Textarea::make('additional_info')
                            ->label('Aanvullende gegevens')
                            ->helperText('Wordt getoond aan klanten wanneer zij een betaalmethode kiezen')
                            ->rows(2)
                            ->maxLength(1250)
                            ->rules([
                                'nullable',
                                'max:1250',
                            ]),
                        Textarea::make('payment_instructions')
                            ->label('Betalingsinstructies')
                            ->helperText('Wordt getoond aan klanten wanneer zij een bestelling hebben geplaatst met deze betaalmethode')
                            ->rows(2)
                            ->maxLength(1250)
                            ->rules([
                                'nullable',
                                'max:1250',
                            ]),
                        TextInput::make('extra_costs')
                            ->label('Extra kosten wanneer deze betalingsmethode wordt gekozen')
                            ->rules([
                                'numeric',
                                'max:255',
                            ]),
                        TextInput::make('available_from_amount')
                            ->label('Vanaf hoeveel € moet deze betaalmethode beschikbaar zijn')
                            ->rules([
                                'numeric',
                                'max:255',
                            ]),
                        TextInput::make('deposit_calculation')
                            ->label('Calculatie voor de aanbetaling met deze betaalmethode (leeg = geen aanbetaling), let op: hiervoor moet je een PSP gekoppeld hebben & dit werkt niet bij het aanmaken van handmatige orders')
                            ->helperText('Variables: {ORDER_TOTAL} {ORDER_TOTAL_MINUS_PAYMENT_COSTS}')
                            ->maxLength(255)
                            ->rules([
                                'nullable',
                                'max:255',
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
                    ->sortable(),
                TextColumn::make('site_id')
                    ->label('Actief op site')
                    ->sortable()
                    ->hidden(! (Sites::getAmountOfSites() > 1))
                    ->searchable(),
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
