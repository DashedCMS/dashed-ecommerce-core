<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources;

use UnitEnum;
use BackedEnum;
use Filament\Tables\Table;
use Filament\Schemas\Schema;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Resource;
use Filament\Actions\DeleteAction;
use Illuminate\Support\Facades\DB;
use Dashed\DashedCore\Classes\Sites;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Fieldset;
use Filament\Infolists\Components\TextEntry;
use Dashed\DashedEcommerceCore\Models\Product;
use Filament\Schemas\Components\Utilities\Get;
use Dashed\DashedEcommerceCore\Models\DiscountCode;
use Dashed\DashedEcommerceCore\Models\ProductCategory;
use Dashed\DashedCore\Classes\Actions\ActionGroups\ToolbarActions;
use Dashed\DashedEcommerceCore\Filament\Resources\GiftcardResource\Pages\EditGiftcard;
use Dashed\DashedEcommerceCore\Filament\Resources\GiftcardResource\Pages\ViewGiftcard;
use Dashed\DashedEcommerceCore\Filament\Resources\GiftcardResource\Pages\ListGiftcards;
use Dashed\DashedEcommerceCore\Filament\Resources\GiftcardResource\Pages\CreateGiftcard;

class GiftcardResource extends Resource
{
    use \Dashed\DashedCore\Filament\Concerns\HasLastEditedColumn;

    protected static ?string $model = DiscountCode::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-gift-top';
    protected static string | UnitEnum | null $navigationGroup = 'E-commerce';
    protected static ?string $navigationLabel = 'Cadeaukaarten';
    protected static ?string $label = 'Cadeaukaart';
    protected static ?string $pluralLabel = 'Cadeaukaarten';
    protected static ?int $navigationSort = 50;

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

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make(__('Content'))
                    ->schema(
                        array_merge([
                            Select::make('site_ids')
                                ->multiple()
                                ->label(__('Actief op sites'))
                                ->options(collect(Sites::getSites())->pluck('name', 'id')->toArray())
                                ->hidden(function () {
                                    return ! (Sites::getAmountOfSites() > 1);
                                })
                                ->required(),
                            TextInput::make('name')
                                ->label(__('Naam'))
                                ->required()
                                ->maxLength(100),
                            TextInput::make('code')
                                ->label(__('Code'))
                                ->helperText(__('Deze code vullen mensen in om af te rekenen.'))
                                ->required()
                                ->unique('dashed__discount_codes', 'code', fn ($record) => $record)
                                ->hidden(fn (Get $get) => $get('is_global_discount'))
                                ->minLength(3)
                                ->maxLength(100),
                            Toggle::make('create_multiple_codes')
                                ->label(__('Meerdere codes aanmaken'))
                                ->reactive()
                                ->visible(fn ($livewire, Get $get) => $livewire instanceof CreateGiftcard),
                            TextInput::make('amount_of_codes')
                                ->label(__('Hoeveel cadeaukaarten moeten er aangemaakt worden'))
                                ->helperText(__('Gebruik een * in de cadeaukaart om een willekeurige letter of getal neer te zetten. Gebruik er minstens 5! Voorbeeld: SITE*****ACTIE'))
                                ->type('number')
                                ->required()
                                ->maxValue(500)
                                ->visible(fn ($livewire, Get $get) => $get('create_multiple_codes') && $livewire instanceof CreateGiftcard),
                            Textarea::make('note')
                                ->label(__('Notitie'))
                                ->helperText(__('Notitie voor intern gebruik'))
                                ->maxLength(5000)
                                ->columnSpanFull(),
                        ])
                    )
                    ->columnSpanFull()
                    ->columns(2),
                Section::make(__('Informatie'))
                    ->schema(array_merge([
                        TextInput::make('discount_amount')
                            ->label(__('Waarde van de cadeaukaart'))
                            ->helperText(__('Hoeveel euro moet er op deze cadeaukaart staan'))
                            ->prefix(__('€'))
                            ->minValue(0)
                            ->maxValue(100000)
                            ->numeric()
                            ->required(),
                        Radio::make('valid_for')
                            ->label(__('Van toepassing op'))
                            ->reactive()
                            ->options([
                                null => __('Alle producten'),
                                'products' => __('Specifieke producten'),
                                'categories' => __('Specifieke categorieën'),
                            ]),
                        Select::make('products')
                            ->relationship('products', 'name')
                            ->multiple()
                            ->getSearchResultsUsing(fn (string $search) => Product::where(DB::raw('lower(name)'), 'like', '%' . strtolower($search) . '%')->limit(50)->pluck('name', 'id'))
                            ->getOptionLabelFromRecordUsing(fn ($record) => $record->nameWithParents)
                            ->label(__('Selecteer producten waar deze cadeaukaart voor geldt'))
                            ->required()
                            ->hidden(fn (Get $get) => $get('valid_for') != 'products'),
                        Select::make('productCategories')
                            ->relationship('productCategories', 'name')
                            ->multiple()
                            ->getSearchResultsUsing(fn (string $search) => ProductCategory::where(DB::raw('lower(name)'), 'like', '%' . strtolower($search) . '%')->limit(50)->pluck('name', 'id'))
                            ->getOptionLabelFromRecordUsing(fn ($record) => $record->nameWithParents)
                            ->label(__('Selecteer categorieën waar deze cadeaukaart voor geldt'))
                            ->required(fn (Get $get) => $get('valid_for') == 'categories')
                            ->hidden(fn (Get $get) => $get('valid_for') != 'categories'),
                        Radio::make('minimal_requirements')
                            ->label(__('Minimale eisen'))
                            ->reactive()
                            ->options([
                                null => __('Geen'),
                                'products' => __('Minimaal aantal producten'),
                                'amount' => __('Minimaal aankoopbedrag'),
                            ]),
                        TextInput::make('minimum_products_count')
                            ->label(__('Minimum aantal producten'))
                            ->type('number')
                            ->minValue(1)
                            ->maxValue(100000)
                            ->numeric()
                            ->required()
                            ->hidden(fn ($get) => $get('minimal_requirements') != 'products'),
                        TextInput::make('minimum_amount')
                            ->label(__('Minimum aankoopbedrag'))
                            ->prefix(__('€'))
                            ->minValue(1)
                            ->maxValue(100000)
                            ->required()
                            ->numeric()
                            ->hidden(fn (Get $get) => $get('minimal_requirements') != 'amount'),
                    ]))
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->query(DiscountCode::isGiftcard())
            ->columns([
                TextColumn::make('name')
                    ->label(__('Naam'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('code')
                    ->label(__('Code'))
                    ->default('-')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('discount_amount')
                    ->label(__('Huidig'))
                    ->default('-')
                    ->money('EUR')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('initial_amount')
                    ->label(__('Initieel'))
                    ->default('-')
                    ->money('EUR')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('reserved_amount')
                    ->label(__('Gereserveerd'))
                    ->default('-')
                    ->money('EUR')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('used_amount')
                    ->label(__('Gebruikt'))
                    ->default('-')
                    ->money('EUR')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('site_ids')
                    ->label(__('Actief op site(s)'))
                    ->sortable()
                    ->badge()
                    ->hidden(! (Sites::getAmountOfSites() > 1))
                    ->searchable(),
                TextColumn::make('amountOfUses')
                    ->label(__('Aantal gebruiken'))
                    ->getStateUsing(function ($record) {
                        return "{$record->stock_used}x gebruikt";
                    }),
                TextColumn::make('created_at')
                    ->label(__('Aangemaakt op'))
                    ->dateTime()
                    ->sortable(),
                static::lastEditedColumn(),
            ])
            ->modifyQueryUsing(fn ($query) => static::modifyTableQueryForLastEdited($query))
            ->defaultSort('created_at', 'DESC')
            ->filters([
                \Filament\Tables\Filters\TernaryFilter::make('has_balance')
                    ->label(__('Met restsaldo'))
                    ->queries(
                        true: fn (\Illuminate\Database\Eloquent\Builder $q) => $q->where('discount_amount', '>', 0),
                        false: fn (\Illuminate\Database\Eloquent\Builder $q) => $q->where('discount_amount', '<=', 0),
                        blank: fn (\Illuminate\Database\Eloquent\Builder $q) => $q,
                    ),
            ])
            ->recordActions([
                ViewAction::make()
                    ->button(),
                EditAction::make()
                    ->button(),
                DeleteAction::make(),
            ])
            ->toolbarActions(ToolbarActions::getActions());
    }

    public static function infolist(Schema $schema): Schema
    {
        $logsSchema = [];

        return $schema
            ->schema([
                Fieldset::make(__('Cadeaukaart informatie'))
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('name')
                            ->label(__('Naam')),
                        TextEntry::make('code')
                            ->label(__('Code')),
                        TextEntry::make('created_at')
                            ->label(__('Aangemaakt op'))
                            ->dateTime(),
                        TextEntry::make('updated_at')
                            ->label(__('Laatst aangepast op'))
                            ->dateTime(),
                        TextEntry::make('user_id')
                            ->label(__('Aangemaakt door'))
                            ->getStateUsing(function ($record) {
                                return $record->user ? $record->user->name : 'Systeem';
                            }),
                        TextEntry::make('site_ids')
                            ->label(__('Actief op site(s)'))
                            ->hidden(! (Sites::getAmountOfSites() > 1))
                            ->getStateUsing(function ($record) {
                                $siteNames = [];
                                foreach (Sites::getSites() as $site) {
                                    if (in_array($site['id'], $record->site_ids ?: [])) {
                                        $siteNames[] = $site['name'];
                                    }
                                }

                                return implode(', ', $siteNames);
                            }),
                    ]),
                Fieldset::make(__('Waarde'))
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('discount_amount')
                            ->label(__('Huidige waarde'))
                            ->money('EUR'),
                        TextEntry::make('initial_amount')
                            ->label(__('Initiele waarde'))
                            ->money('EUR'),
                        TextEntry::make('reserved_amount')
                            ->label(__('Gereserveerde waarde'))
                            ->helperText(__('Dit is de waarde die momenteel in gebruik is in openstaande bestellingen.'))
                            ->money('EUR'),
                        TextEntry::make('used_amount')
                            ->label(__('Gebruikte waarde'))
                            ->helperText(__('Dit is de waarde die al gebruikt is in afgeronde bestellingen.'))
                            ->money('EUR'),
                    ]),
                Fieldset::make(__('Logboek'))
                    ->columnSpanFull()
                    ->schema(function ($record) {
                        $schema = [];

                        foreach ($record->logs as $log) {
                            $schema[] = Fieldset::make(__('Log van :datum', ['datum' => $log->created_at->format('d-m-Y H:i')]))
                                ->schema([
                                    TextEntry::make('tag_' . $log->id)
                                        ->label(__('Log'))
                                        ->default($log->tag()),
                                    TextEntry::make('created_at_' . $log->id)
                                        ->label(__('Log aangemaakt op'))
                                        ->dateTime()
                                        ->default($log->created_at),
                                    TextEntry::make('user_id_' . $log->id)
                                        ->label(__('Door'))
                                        ->columnSpanFull()
                                        ->default($log->user ? $log->user->name : 'Systeem'),
                                    TextEntry::make('old_amount_' . $log->id)
                                        ->label(__('Oude waarde'))
                                        ->money('EUR')
                                        ->default($log->old_amount),
                                    TextEntry::make('new_amount_' . $log->id)
                                        ->label(__('Nieuwe waarde'))
                                        ->money('EUR')
                                        ->default($log->new_amount),
                                ])
                                ->columnSpanFull()
                                ->columns(2);
                        }

                        return array_reverse($schema);
                    }),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            \Dashed\DashedEcommerceCore\Filament\Resources\DiscountCodeResource\RelationManagers\OrdersRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListGiftcards::route('/'),
            'create' => CreateGiftcard::route('/create'),
            'edit' => EditGiftcard::route('/{record}/edit'),
            'view' => ViewGiftcard::route('/{record}/view'),
        ];
    }
}
