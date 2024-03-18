<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources;

use Filament\Forms\Form;
use Filament\Tables\Table;
use Filament\Resources\Resource;
use Dashed\DashedCore\Classes\Sites;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Section;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Resources\Concerns\Translatable;
use Filament\Tables\Actions\DeleteBulkAction;
use Dashed\DashedEcommerceCore\Classes\Countries;
use Dashed\DashedEcommerceCore\Models\ShippingZone;
use Dashed\DashedEcommerceCore\Classes\PaymentMethods;
use Dashed\DashedCore\Classes\QueryHelpers\SearchQuery;
use Dashed\DashedEcommerceCore\Filament\Resources\ShippingZoneResource\Pages\EditShippingZone;
use Dashed\DashedEcommerceCore\Filament\Resources\ShippingZoneResource\Pages\ListShippingZones;
use Dashed\DashedEcommerceCore\Filament\Resources\ShippingZoneResource\Pages\CreateShippingZone;

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
                            ->hidden(! (Sites::getAmountOfSites() > 1))
                            ->required(),
                    ])
                    ->hidden(! (Sites::getAmountOfSites() > 1))
                    ->collapsed(fn ($livewire) => $livewire instanceof EditShippingZone),
                Section::make('Content')
                    ->schema([
                        TextInput::make('name')
                            ->label('Name')
                            ->required()
                            ->maxLength(100),
                        TextInput::make('search_fields')
                            ->label('Voer extra woorden in waarop deze verzendzone geactiveerd mag worden, woorden scheiden met een komma')
                            ->maxLength(255),
                        Toggle::make('hide_vat_on_invoice')
                            ->label('Verberg BTW op de factuur bij het kiezen van deze verzendzone'),
                        Select::make('zones')
                            ->label('Geactiveerde regio\'s')
                            ->multiple()
                            ->options(function () {
                                $countries = [];
                                foreach (collect(Countries::getCountries()) as $country) {
                                    $countries[$country['name']] = $country['name'];
                                }

                                return $countries;
                            })
                            ->required(),
                        Select::make('disabled_payment_method_ids')
                            ->label('Deactiveer betalingsmethodes voor deze verzendzone')
                            ->multiple()
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
                    ->sortable()
                    ->searchable(query: SearchQuery::make()),
                TextColumn::make('site_id')
                    ->label('Actief op site')
                    ->sortable()
                    ->hidden(! (Sites::getAmountOfSites() > 1)),
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
            'index' => ListShippingZones::route('/'),
            'create' => CreateShippingZone::route('/create'),
            'edit' => EditShippingZone::route('/{record}/edit'),
        ];
    }
}
