<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources;

use Filament\Resources\Form;
use Filament\Resources\Table;
use Filament\Resources\Resource;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\TextColumn;
use Dashed\DashedCore\Classes\Sites;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Concerns\Translatable;
use Dashed\DashedEcommerceCore\Models\ShippingClass;
use Dashed\DashedEcommerceCore\Filament\Resources\ShippingClassResource\Pages\EditShippingClass;
use Dashed\DashedEcommerceCore\Filament\Resources\ShippingClassResource\Pages\CreateShippingClass;
use Dashed\DashedEcommerceCore\Filament\Resources\ShippingClassResource\Pages\ListShippingClasses;

class ShippingClassResource extends Resource
{
    use Translatable;

    protected static ?string $model = ShippingClass::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static bool $shouldRegisterNavigation = false;
    protected static ?string $navigationIcon = 'heroicon-o-truck';
    protected static ?string $navigationGroup = 'Content';
    protected static ?string $navigationLabel = 'Verzendklasses';
    protected static ?string $label = 'Verzendklas';
    protected static ?string $pluralLabel = 'Verzendklasses';

    public static function getGloballySearchableAttributes(): array
    {
        return [
            'name',
            'description',
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
            'index' => ListShippingClasses::route('/'),
            'create' => CreateShippingClass::route('/create'),
            'edit' => EditShippingClass::route('/{record}/edit'),
        ];
    }
}
