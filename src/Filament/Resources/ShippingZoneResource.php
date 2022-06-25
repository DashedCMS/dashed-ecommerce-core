<?php

namespace Qubiqx\QcommerceEcommerceCore\Filament\Resources;

use Filament\Resources\Form;
use Filament\Resources\Table;
use Filament\Resources\Resource;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Section;
use Filament\Tables\Columns\TextColumn;
use Qubiqx\QcommerceCore\Classes\Sites;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\MultiSelect;
use Filament\Resources\Concerns\Translatable;
use Qubiqx\QcommerceEcommerceCore\Classes\Countries;
use Qubiqx\QcommerceEcommerceCore\Models\ShippingZone;
use Qubiqx\QcommerceEcommerceCore\Classes\PaymentMethods;
use Qubiqx\QcommerceEcommerceCore\Filament\Resources\ShippingZoneResource\Pages\EditShippingZone;
use Qubiqx\QcommerceEcommerceCore\Filament\Resources\ShippingZoneResource\Pages\ListShippingZones;
use Qubiqx\QcommerceEcommerceCore\Filament\Resources\ShippingZoneResource\Pages\CreateShippingZone;

class ShippingZoneResource extends Resource
{
    use Translatable;

    protected static ?string $model = ShippingZone::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static bool $shouldRegisterNavigation = false;
    protected static ?string $navigationIcon = 'heroicon-o-truck';
    protected static ?string $navigationGroup = 'Content';
    protected static ?string $navigationLabel = 'Verzendzones';
    protected static ?string $label = 'Verzendzone';
    protected static ?string $pluralLabel = 'Verzendzones';

    public static function getGloballySearchableAttributes(): array
    {
        return [
            'name',
            'zones',
            'search_fields',
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
                                return !(Sites::getAmountOfSites() > 1);
                            })
                            ->required(),
                    ])
                    ->collapsed(fn($livewire) => $livewire instanceof EditShippingZone),
                Section::make('Content')
                    ->schema([
                        TextInput::make('name')
                            ->label('Name')
                            ->required()
                            ->maxLength(100)
                            ->rules([
                                'max:100',
                            ]),
                        TextInput::make('search_fields')
                            ->label('Voer extra woorden in waarop deze verzendzone geactiveerd mag worden, woorden scheiden met een komma')
                            ->maxLength(255)
                            ->rules([
                                'max:255',
                            ]),
                        Toggle::make('hide_vat_on_invoice')
                            ->label('Verberg BTW op de factuur bij het kiezen van deze verzendzone'),
                        MultiSelect::make('zones')
                            ->label('Geactiveerde regio\'s')
                            ->options(function () {
                                $countries = [];
                                foreach (collect(Countries::getCountries()) as $country) {
                                    $countries[$country['name']] = $country['name'];
                                }

                                return $countries;
                            })
                            ->required(),
                        MultiSelect::make('disabled_payment_method_ids')
                            ->label('Deactiveer betalingsmethodes voor deze verzendzone')
                            ->options(collect(PaymentMethods::get())->pluck('name', 'id')->toArray()),
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
                    ->hidden(!(Sites::getAmountOfSites() > 1))
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
            'index' => ListShippingZones::route('/'),
            'create' => CreateShippingZone::route('/create'),
            'edit' => EditShippingZone::route('/{record}/edit'),
        ];
    }
}
