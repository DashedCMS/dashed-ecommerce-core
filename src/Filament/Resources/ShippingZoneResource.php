<?php

namespace Qubiqx\QcommerceEcommerceCore\Filament\Resources;

use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Concerns\Translatable;
use Filament\Resources\Form;
use Filament\Resources\Resource;
use Filament\Resources\Table;
use Filament\Tables\Columns\TextColumn;
use Qubiqx\QcommerceCore\Classes\Sites;
use Qubiqx\QcommerceEcommerceCore\Filament\Resources\ShippingClassResource\Pages\EditShippingClass;
use Qubiqx\QcommerceEcommerceCore\Filament\Resources\ShippingZoneResource\Pages\CreateShippingZone;
use Qubiqx\QcommerceEcommerceCore\Filament\Resources\ShippingZoneResource\Pages\EditShippingZone;
use Qubiqx\QcommerceEcommerceCore\Filament\Resources\ShippingZoneResource\Pages\ListShippingZones;
use Qubiqx\QcommerceEcommerceCore\Models\ShippingZone;

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
                                return ! (Sites::getAmountOfSites() > 1);
                            })
                            ->required(),
                    ])
                    ->collapsed(fn ($livewire) => $livewire instanceof EditShippingClass),
                Section::make('Content')
                    ->schema([
                        TextInput::make('name')
                            ->label('Name')
                            ->required()
                            ->maxLength(100)
                            ->rules([
                                'max:100',
                            ]),
                        Textarea::make('description')
                            ->label('Beschrijving')
                            ->helperText('Alleen intern gebruik')
                            ->rows(2)
                            ->maxLength(1250)
                            ->rules([
                                'nullable',
                                'max:1250',
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
            'index' => ListShippingZones::route('/'),
            'create' => CreateShippingZone::route('/create'),
            'edit' => EditShippingZone::route('/{record}/edit'),
        ];
    }
}
