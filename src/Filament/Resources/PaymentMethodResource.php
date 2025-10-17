<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources;

use Filament\Forms\Get;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Filament\Resources\Resource;
use Dashed\DashedCore\Classes\Sites;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Resources\Concerns\Translatable;
use Filament\Tables\Actions\DeleteBulkAction;
use Dashed\DashedEcommerceCore\Models\PinTerminal;
use Dashed\DashedEcommerceCore\Models\PaymentMethod;
use Dashed\DashedEcommerceCore\Classes\PaymentMethods;
use Dashed\DashedCore\Classes\QueryHelpers\SearchQuery;
use Dashed\DashedEcommerceCore\Filament\Resources\PaymentMethodResource\Pages\EditPaymentMethod;
use Dashed\DashedEcommerceCore\Filament\Resources\PaymentMethodResource\Pages\ListPaymentMethods;
use Dashed\DashedEcommerceCore\Filament\Resources\PaymentMethodResource\Pages\CreatePaymentMethod;

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
                ->maxLength(100),
            TextInput::make('psp')
                ->label('PSP')
                ->disabled(),
            TextInput::make('psp_id')
                ->label('PSP ID')
                ->disabled(),
            Toggle::make('active')
                ->label('Actief')
                ->default(1),
            Toggle::make('is_cash_payment')
                ->label('Dit is een contante betalingsmethode')
                ->default(0),
            Select::make('type')
                ->label('Type')
                ->helperText('Waarvoor is deze betaalmethode?')
                ->default('online')
                ->options(PaymentMethods::getTypes())
                ->reactive()
                ->required(),
            Select::make('pin_terminal_id')
                ->label('PIN terminal')
                ->helperText('Pin terminal')
                ->visible(fn (Get $get) => $get('type') == 'pos')
                ->options(fn () => PinTerminal::active()->get()->pluck('name', 'id')->toArray())
                ->searchable()
                ->preload(),
            Toggle::make('postpay')
                ->label('Achteraf betaalmethode')
                ->hidden(fn ($record) => $record && $record->psp == 'own'),
            Textarea::make('additional_info')
                ->label('Aanvullende gegevens')
                ->helperText('Wordt getoond aan klanten wanneer zij een betaalmethode kiezen')
                ->rows(2)
                ->maxLength(1250),
            Textarea::make('payment_instructions')
                ->label('Betalingsinstructies')
                ->helperText('Wordt getoond aan klanten wanneer zij een bestelling hebben geplaatst met deze betaalmethode')
                ->rows(2)
                ->maxLength(1250),
            mediaHelper()->field('image', 'Afbeelding / icon', isImage: true),
            TextInput::make('extra_costs')
                ->label('Extra kosten wanneer deze betalingsmethode wordt gekozen')
                ->maxValue(100000)
                ->numeric()
                ->required()
                ->default(0),
            TextInput::make('available_from_amount')
                ->label('Vanaf hoeveel € moet deze betaalmethode beschikbaar zijn')
                ->numeric()
                ->maxValue(100000)
                ->required()
                ->default(0),
            TextInput::make('deposit_calculation')
                ->label('Calculatie voor de aanbetaling met deze betaalmethode (leeg = geen aanbetaling), let op: hiervoor moet je een PSP gekoppeld hebben & dit werkt niet bij het aanmaken van handmatige orders')
                ->helperText('Variables: {ORDER_TOTAL} {ORDER_TOTAL_MINUS_PAYMENT_COSTS}')
                ->maxLength(255)
                ->reactive()
                ->hidden(fn ($record) => ! $record || ($record && $record->psp != 'own')),
            Select::make('deposit_calculation_payment_method_ids')
                ->multiple()
                ->label('Vink de betaalmethodes aan waarmee een aanbetaling voldaan mag worden')
                ->options(PaymentMethod::where('psp', '!=', 'own')->pluck('name', 'id')->toArray())
                ->hidden(fn ($record, Get $get) => (! $record || ($record && $record->psp != 'own')) || ! $get('deposit_calculation')),
            Select::make('users')
                ->relationship('users')
                ->getOptionLabelFromRecordUsing(fn ($record) => $record->name)
                ->multiple()
                ->preload()
                ->searchable()
                ->label('Wie mag deze betaalmethode gebruiken?')
            ->helperText('Leeg = iedereen mag deze betaalmethode gebruiken'),
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
                Section::make('Betaalmethode')
                    ->schema($contentSchema),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Naam')
                    ->searchable(query: SearchQuery::make())
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
                    ->label('Afbeelding')
                    ->getStateUsing(fn ($record) => $record->image ? (mediaHelper()->getSingleMedia($record->image)->url ?? '') : ''),
                IconColumn::make('active')
                    ->label('Actief')
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle'),

            ])
            ->filters([
                //
            ])
            ->reorderable('order')
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
            'index' => ListPaymentMethods::route('/'),
            'create' => CreatePaymentMethod::route('/create'),
            'edit' => EditPaymentMethod::route('/{record}/edit'),
        ];
    }
}
