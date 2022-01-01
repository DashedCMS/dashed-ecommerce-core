<?php

namespace Qubiqx\QcommerceEcommerceCore\Filament\Resources;

use Filament\Resources\Form;
use Filament\Resources\Table;
use Filament\Resources\Resource;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\TextColumn;
use Qubiqx\QcommerceCore\Classes\Sites;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\ImageColumn;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\MultiSelect;
use Filament\Tables\Columns\BooleanColumn;
use Filament\Resources\Concerns\Translatable;
use Qubiqx\QcommerceEcommerceCore\Models\PaymentMethod;
use Qubiqx\QcommerceEcommerceCore\Filament\Resources\PaymentMethodResource\Pages\EditPaymentMethod;
use Qubiqx\QcommerceEcommerceCore\Filament\Resources\PaymentMethodResource\Pages\ListPaymentMethods;
use Qubiqx\QcommerceEcommerceCore\Filament\Resources\PaymentMethodResource\Pages\CreatePaymentMethod;

class PaymentMethodResource extends Resource
{
    use Translatable;

    protected static ?string $model = PaymentMethod::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static bool $shouldRegisterNavigation = false;
    protected static ?string $navigationIcon = 'heroicon-o-credit-card';
    protected static ?string $navigationGroup = 'Content';
    protected static ?string $navigationLabel = 'Betaalmethodes';
    protected static ?string $label = 'Betaalmethode';
    protected static ?string $pluralLabel = 'Betaalmethodes';

    public static function getGloballySearchableAttributes(): array
    {
        return [
            'name',
        ];
    }

    public static function form(Form $form): Form
    {
        $contentSchema = [
            TextInput::make('name')
                ->label('Name')
                ->required()
                ->maxLength(100)
                ->rules([
                    'max:100',
                ]),
            Toggle::make('active')
                ->label('Actief')
            ->default(1),
            Toggle::make('postpay')
                ->label('Achteraf betaalmethode')
                ->hidden(fn ($record) => $record && $record->psp == 'own'),
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
            FileUpload::make('image')
                ->label('Afbeelding / icon')
                ->directory('qcommerce/payment-methods')
                ->image(),
            TextInput::make('extra_costs')
                ->label('Extra kosten wanneer deze betalingsmethode wordt gekozen')
                ->rules([
                    'required',
                    'numeric',
                    'max:255',
                ])
            ->required()
            ->default(0),
            TextInput::make('available_from_amount')
                ->label('Vanaf hoeveel € moet deze betaalmethode beschikbaar zijn')
                ->rules([
                    'required',
                    'numeric',
                    'max:255',
                ])
            ->required()
            ->default(0),
            TextInput::make('deposit_calculation')
                ->label('Calculatie voor de aanbetaling met deze betaalmethode (leeg = geen aanbetaling), let op: hiervoor moet je een PSP gekoppeld hebben & dit werkt niet bij het aanmaken van handmatige orders')
                ->helperText('Variables: {ORDER_TOTAL} {ORDER_TOTAL_MINUS_PAYMENT_COSTS}')
                ->maxLength(255)
                ->rules([
                    'nullable',
                    'max:255',
                ])
                ->reactive()
                ->hidden(fn ($record) => ! $record || ($record && $record->psp != 'own')),
            MultiSelect::make('deposit_calculation_payment_method_ids')
                ->label('Vink de betaalmethodes aan waarmee een aanbetaling voldaan mag worden')
                ->options(PaymentMethod::where('psp', '!=', 'own')->pluck('name', 'id')->toArray())
                ->hidden(fn ($record, \Closure $get) => (! $record || ($record && $record->psp != 'own')) || ! $get('deposit_calculation')),
        ];

        return $form
            ->schema([
                Section::make('Globale informatie')
                    ->schema([
                        Select::make('site_id')
                            ->label('Actief op site')
                            ->options(collect(Sites::getSites())->pluck('name', 'id')->toArray())
                            ->hidden(function () {
                                return ! (Sites::getAmountOfSites() > 1);
                            })
                            ->required(),
                    ])
                    ->hidden(function () {
                        return ! (Sites::getAmountOfSites() > 1);
                    })
                    ->collapsed(fn ($livewire) => $livewire instanceof EditPaymentMethod),
                Section::make('Content')
                    ->schema($contentSchema),
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
                TextColumn::make('site_id')
                    ->label('Actief op site')
                    ->sortable()
                    ->hidden(! (Sites::getAmountOfSites() > 1))
                    ->searchable(),
                TextColumn::make('psp')
                    ->label('PSP')
                    ->sortable()
                    ->searchable(),
                ImageColumn::make('image')
                    ->label('Afbeelding'),
                BooleanColumn::make('active')
                    ->label('Actief'),

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
            'index' => ListPaymentMethods::route('/'),
            'create' => CreatePaymentMethod::route('/create'),
            'edit' => EditPaymentMethod::route('/{record}/edit'),
        ];
    }
}
