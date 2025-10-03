<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources;

use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Models\ProductGroup;
use Filament\Actions\Action;
use Filament\Forms\Get;
use Filament\Forms\Form;
use Illuminate\Support\Facades\DB;
use Filament\Forms\Set;
use Filament\Tables\Table;
use Filament\Resources\Resource;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Repeater;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Resources\Concerns\Translatable;
use Filament\Tables\Actions\DeleteBulkAction;
use Dashed\DashedEcommerceCore\Models\ShippingClass;
use Dashed\DashedEcommerceCore\Models\ShippingMethod;
use Dashed\DashedCore\Classes\QueryHelpers\SearchQuery;
use Dashed\DashedEcommerceCore\Filament\Resources\ShippingMethodResource\Pages\EditShippingMethod;
use Dashed\DashedEcommerceCore\Filament\Resources\ShippingMethodResource\Pages\ListShippingMethods;
use Dashed\DashedEcommerceCore\Filament\Resources\ShippingMethodResource\Pages\CreateShippingMethod;

class ShippingMethodResource extends Resource
{
    use Translatable;

    protected static ?string $model = ShippingMethod::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static bool $shouldRegisterNavigation = false;
    protected static ?string $navigationIcon = 'heroicon-o-truck';
    protected static ?string $navigationGroup = 'Content';
    protected static ?string $navigationLabel = 'Verzendmethodes';
    protected static ?string $label = 'Verzendmethode';
    protected static ?string $pluralLabel = 'Verzendmethodes';

    public static function getGloballySearchableAttributes(): array
    {
        return [
            'name',
        ];
    }

    public static function form(Form $form): Form
    {
        $schema = [
            Section::make('Globale informatie')
                ->schema([
                    Select::make('shipping_zone_id')
                        ->relationship('shippingZone', 'name')
                        ->label('Hangt onder verzendzone')
                        ->getOptionLabelFromRecordUsing(fn($record) => $record->name)
                        ->required(),
                ])
                ->collapsed(fn($livewire) => $livewire instanceof EditShippingMethod),
            Section::make('Content')
                ->schema([
                    TextInput::make('name')
                        ->label('Name')
                        ->required()
                        ->maxLength(100),
                    Radio::make('sort')
                        ->label('Soort verzendmethod')
                        ->options([
                            'static_amount' => 'Vast bedrag',
                            'variable_amount' => 'Variabel bedrag',
                            'free_delivery' => 'Gratis verzending',
                            'take_away' => 'Afhalen',
                        ])
                        ->reactive()
                        ->required(),
                    TextInput::make('minimum_order_value')
                        ->label('Vanaf hoeveel € moet deze verzendmethode geldig zijn')
                        ->required()
                        ->numeric(),
                    TextInput::make('maximum_order_value')
                        ->label('Tot hoeveel € moet deze verzendmethode geldig zijn (zet op 100000 voor oneindig)')
                        ->required()
                        ->numeric()
                        ->maxValue(100000),
                    TextInput::make('costs')
                        ->label('Kosten van deze verzendmethode')
                        ->required()
                        ->numeric()
                        ->hidden(fn($get) => $get('sort') == 'free_delivery' || $get('sort') == 'variable_amount'),
                    Repeater::make('variables')
                        ->label('Extra vaste kosten van deze verzendmethode')
                        ->helperText('Met variable berekening kan je per x aantal items rekenen, we rekenen van boven naar beneden')
                        ->schema([
                            TextInput::make('amount_of_items')
                                ->label('Voor hoeveel stuks moet dit gelden')
                                ->type('number')
                                ->numeric(),
                            TextInput::make('costs')
                                ->label('Vul een prijs in voor dit aantal')
                                ->numeric(),
                        ])
                        ->nullable()
                        ->hidden(fn($get) => $get('sort') != 'variable_amount'),
                    TextInput::make('variable_static_costs')
                        ->label('Extra vaste kosten van deze verzendmethode')
                        ->helperText('Deze berekening wordt bovenop de kosten hierboven gedaan, variablen om te gebruiken: {SHIPPING_COSTS}')
                        ->maxLength(255)
                        ->hidden(fn($get) => $get('sort') != 'variable_amount'),
                    Toggle::make('distance_range_enabled')
                        ->label('Alleen beschikbaar voor aantal KMs vanaf vestiging')
                        ->helperText('Google API key moet gekoppeld zijn voor dit om te werken')
                        ->reactive(),
                    TextInput::make('distance_range')
                        ->label('Aantal KMs vanaf vestiging mogelijk')
                        ->numeric()
                        ->required()
                        ->visible(fn(Get $get) => $get('distance_range_enabled')),
                    Select::make('disabled_product_ids')
                        ->relationship('disabledProducts', 'name')
                        ->label('Deactiveer deze verzendmethode voor deze producten')
                        ->getSearchResultsUsing(fn(string $search) => Product::where(DB::raw('lower(name)'), 'like', '%' . strtolower($search) . '%')->limit(50)->pluck('name', 'id'))
                        ->searchable()
                        ->multiple()
                        ->preload()
                        ->getOptionLabelFromRecordUsing(fn($record) => $record->nameWithParents)
                        ->hintAction(
                            \Filament\Forms\Components\Actions\Action::make('addAllProducts')
                                ->label('Voeg alle producten toe')
                                ->action(function (Set $set) {
                                    $set('disabled_product_ids', Product::all()->pluck('id')->toArray());
                                }),
                        ),
                    Select::make('disabled_product_group_ids')
                        ->relationship('disabledProductGroups', 'name')
                        ->label('Deactiveer deze verzendmethode voor deze producten groepen')
                        ->getSearchResultsUsing(fn(string $search) => ProductGroup::where(DB::raw('lower(name)'), 'like', '%' . strtolower($search) . '%')->limit(50)->pluck('name', 'id'))
                        ->searchable()
                        ->multiple()
                        ->preload()
                        ->getOptionLabelFromRecordUsing(fn($record) => $record->nameWithParents)
                        ->hintAction(
                            \Filament\Forms\Components\Actions\Action::make('addAllProductGroups')
                                ->label('Voeg alle producten groepen toe')
                                ->action(function (Set $set) {
                                    $set('disabled_product_group_ids', ProductGroup::all()->pluck('id')->toArray());
                                }),
                        ),
                ]),
        ];

        //        $shippingClasses = [];
        //        foreach (ShippingClass::get() as $shippingClass) {
        //            $shippingClasses[] = TextInput::make("shipping_class_costs_$shippingClass->id")
        //                ->label("Vul een meerprijs in voor producten in deze verzendklasse $shippingClass->name")
        //                ->numeric()
        //                ->hidden(fn ($livewire, $record) => ! ($livewire instanceof EditShippingMethod) || $record->shippingZone->site_id != $shippingClass->site_id);
        //        }
        //
        //        if ($shippingClasses) {
        //            $schema[] = Section::make('Verzendklassen meerprijzen')
        //                ->schema($shippingClasses);
        //        }

        return $form
            ->schema($schema);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Naam')
                    ->sortable()
                    ->searchable(query: SearchQuery::make()),
                TextColumn::make('shippingZone.name')
                    ->label('Verzendzone')
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                EditAction::make()
                    ->button(),
                DeleteAction::make(),
            ])
            ->reorderable('order')
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
            'index' => ListShippingMethods::route('/'),
            'create' => CreateShippingMethod::route('/create'),
            'edit' => EditShippingMethod::route('/{record}/edit'),
        ];
    }
}
