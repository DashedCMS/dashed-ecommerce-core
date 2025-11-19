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
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Dashed\DashedEcommerceCore\Models\ShippingZone;
use Dashed\DashedEcommerceCore\Models\ShippingClass;
use Dashed\DashedCore\Classes\QueryHelpers\SearchQuery;
use LaraZeus\SpatieTranslatable\Resources\Concerns\Translatable;
use Dashed\DashedCore\Classes\Actions\ActionGroups\ToolbarActions;
use Dashed\DashedEcommerceCore\Filament\Resources\ShippingClassResource\Pages\EditShippingClass;
use Dashed\DashedEcommerceCore\Filament\Resources\ShippingClassResource\Pages\CreateShippingClass;
use Dashed\DashedEcommerceCore\Filament\Resources\ShippingClassResource\Pages\ListShippingClasses;

class ShippingClassResource extends Resource
{
    use Translatable;

    protected static ?string $model = ShippingClass::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static bool $shouldRegisterNavigation = false;
    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-truck';
    protected static string | UnitEnum | null $navigationGroup = 'Content';
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

    public static function form(Schema $schema): Schema
    {
        $shippingZoneSchema = [];

        foreach (ShippingZone::all() as $shippingZone) {
            $shippingZoneSchema[] =
                TextInput::make("price_shipping_zone_{$shippingZone->id}")
                    ->label("Meerprijs voor verzending naar {$shippingZone->name}")
                    ->prefix('€')
                    ->minValue(1)
                    ->maxValue(10000)
                    ->numeric();
        }

        return $schema
            ->schema([
                Section::make('Globale informatie')->columnSpanFull()
                    ->schema([
                        Select::make('site_id')
                            ->label('Actief op site')
                            ->options(collect(Sites::getSites())->pluck('name', 'id'))
                            ->hidden(! (Sites::getAmountOfSites() > 1))
                            ->required(),
                    ])
                    ->columnSpanFull()
                    ->hidden(! (Sites::getAmountOfSites() > 1))
                    ->collapsed(fn ($livewire) => $livewire instanceof EditShippingClass),
                Section::make('Content')
                    ->schema(array_merge(array_merge([
                        TextInput::make('name')
                            ->label('Name')
                            ->required()
                            ->maxLength(100),
                        Textarea::make('description')
                            ->label('Beschrijving')
                            ->helperText('Alleen intern gebruik')
                            ->rows(2)
                            ->maxLength(1250),
                    ], $shippingZoneSchema), [
                        Toggle::make("count_per_product")
                            ->label("Tel de meerprijs per product in de winkelwagen")
                            ->helperText('Als iemand dus 3x hetzelfde product besteld met deze verzendklas, wordt de meerprijs 3x geteld.'),
                        Toggle::make("count_once")
                            ->label("Tel de meerprijs maximaal 1x in de winkelwagen"),
                    ]))
                    ->columnSpanFull(),
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
            ->recordActions([
                EditAction::make()
                    ->button(),
                DeleteAction::make(),
            ])
            ->toolbarActions(ToolbarActions::getActions());
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
