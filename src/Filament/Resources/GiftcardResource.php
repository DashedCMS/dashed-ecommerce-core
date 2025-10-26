<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources;

use Dashed\DashedEcommerceCore\Filament\Resources\GiftcardResource\Pages\CreateGiftcard;
use Dashed\DashedEcommerceCore\Filament\Resources\GiftcardResource\Pages\EditGiftcard;
use Dashed\DashedEcommerceCore\Filament\Resources\GiftcardResource\Pages\ListGiftcards;
use Dashed\DashedEcommerceCore\Filament\Resources\GiftcardResource\Pages\ViewGiftcard;
use Filament\Schemas\Components\Utilities\Get;

use Filament\Schemas\Components\Fieldset;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Actions\ViewAction;
use Filament\Tables\Table;
use Filament\Resources\Resource;
use Illuminate\Support\Facades\DB;
use Dashed\DashedCore\Classes\Sites;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Textarea;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteBulkAction;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Models\DiscountCode;
use Dashed\DashedEcommerceCore\Models\ProductCategory;
use UnitEnum;
use BackedEnum;

use Filament\Schemas\Schema;

class GiftcardResource extends Resource
{
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

    public static function form(Schema $schema): Schema{
        return $schema
            ->schema([
                Section::make('Content')
                    ->schema(
                        array_merge([
                            Select::make('site_ids')
                                ->multiple()
                                ->label('Actief op sites')
                                ->options(collect(Sites::getSites())->pluck('name', 'id')->toArray())
                                ->hidden(function () {
                                    return !(Sites::getAmountOfSites() > 1);
                                })
                                ->required(),
                            TextInput::make('name')
                                ->label('Naam')
                                ->required()
                                ->maxLength(100),
                            TextInput::make('code')
                                ->label('Code')
                                ->helperText('Deze code vullen mensen in om af te rekenen.')
                                ->required()
                                ->unique('dashed__discount_codes', 'code', fn($record) => $record)
                                ->hidden(fn(Get $get) => $get('is_global_discount'))
                                ->minLength(3)
                                ->maxLength(100),
                            Toggle::make('create_multiple_codes')
                                ->label('Meerdere codes aanmaken')
                                ->reactive()
                                ->visible(fn($livewire, Get $get) => $livewire instanceof CreateGiftcard),
                            TextInput::make('amount_of_codes')
                                ->label('Hoeveel cadeaukaarten moeten er aangemaakt worden')
                                ->helperText('Gebruik een * in de cadeaukaart om een willekeurige letter of getal neer te zetten. Gebruik er minstens 5! Voorbeeld: SITE*****ACTIE')
                                ->type('number')
                                ->required()
                                ->maxValue(500)
                                ->visible(fn($livewire, Get $get) => $get('create_multiple_codes') && $livewire instanceof CreateGiftcard),
                            Textarea::make('note')
                                ->label('Notitie')
                                ->helperText('Notitie voor intern gebruik')
                                ->maxLength(5000)
                                ->columnSpanFull(),
                        ])
                    )
                    ->columns(2),
                Section::make('Informatie')
                    ->schema(array_merge([
                        TextInput::make('discount_amount')
                            ->label('Waarde van de cadeaukaart')
                            ->helperText('Hoeveel euro moet er op deze cadeaukaart staan')
                            ->prefix('€')
                            ->minValue(0)
                            ->maxValue(100000)
                            ->numeric()
                            ->required(),
                        Radio::make('valid_for')
                            ->label('Van toepassing op')
                            ->reactive()
                            ->options([
                                null => 'Alle producten',
                                'products' => 'Specifieke producten',
                                'categories' => 'Specifieke categorieën',
                            ]),
                        Select::make('products')
                            ->relationship('products', 'name')
                            ->multiple()
                            ->getSearchResultsUsing(fn(string $search) => Product::where(DB::raw('lower(name)'), 'like', '%' . strtolower($search) . '%')->limit(50)->pluck('name', 'id'))
                            ->getOptionLabelFromRecordUsing(fn($record) => $record->nameWithParents)
                            ->label('Selecteer producten waar deze cadeaukaart voor geldt')
                            ->required()
                            ->hidden(fn(Get $get) => $get('valid_for') != 'products'),
                        Select::make('productCategories')
                            ->relationship('productCategories', 'name')
                            ->multiple()
                            ->getSearchResultsUsing(fn(string $search) => ProductCategory::where(DB::raw('lower(name)'), 'like', '%' . strtolower($search) . '%')->limit(50)->pluck('name', 'id'))
                            ->getOptionLabelFromRecordUsing(fn($record) => $record->nameWithParents)
                            ->label('Selecteer categorieën waar deze cadeaukaart voor geldt')
                            ->required(fn(Get $get) => $get('valid_for') == 'categories')
                            ->hidden(fn(Get $get) => $get('valid_for') != 'categories'),
                        Radio::make('minimal_requirements')
                            ->label('Minimale eisen')
                            ->reactive()
                            ->options([
                                null => 'Geen',
                                'products' => 'Minimaal aantal producten',
                                'amount' => 'Minimaal aankoopbedrag',
                            ]),
                        TextInput::make('minimum_products_count')
                            ->label('Minimum aantal producten')
                            ->type('number')
                            ->minValue(1)
                            ->maxValue(100000)
                            ->numeric()
                            ->required()
                            ->hidden(fn($get) => $get('minimal_requirements') != 'products'),
                        TextInput::make('minimum_amount')
                            ->label('Minimum aankoopbedrag')
                            ->prefix('€')
                            ->minValue(1)
                            ->maxValue(100000)
                            ->required()
                            ->numeric()
                            ->hidden(fn(Get $get) => $get('minimal_requirements') != 'amount'),
                    ])),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->query(DiscountCode::isGiftcard())
            ->columns([
                TextColumn::make('name')
                    ->label('Naam')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('code')
                    ->label('Code')
                    ->default('-')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('discount_amount')
                    ->label('Huidig')
                    ->default('-')
                    ->money('EUR')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('initial_amount')
                    ->label('Initieel')
                    ->default('-')
                    ->money('EUR')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('reserved_amount')
                    ->label('Gereserveerd')
                    ->default('-')
                    ->money('EUR')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('used_amount')
                    ->label('Gebruikt')
                    ->default('-')
                    ->money('EUR')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('site_ids')
                    ->label('Actief op site(s)')
                    ->sortable()
                    ->badge()
                    ->hidden(!(Sites::getAmountOfSites() > 1))
                    ->searchable(),
                TextColumn::make('amountOfUses')
                    ->label('Aantal gebruiken')
                    ->getStateUsing(function ($record) {
                        return "{$record->stock_used}x gebruikt";
                    }),
                TextColumn::make('created_at')
                    ->label('Aangemaakt op')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'DESC')
            ->filters([
                //
            ])
            ->recordActions([
                ViewAction::make()
                    ->button(),
                EditAction::make()
                    ->button(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        $logsSchema = [];

        return $schema
            ->schema([
                Fieldset::make('Cadeaukaart informatie')
                    ->schema([
                        TextEntry::make('name')
                            ->state('Naam'),
                        TextEntry::make('code')
                            ->state('Code'),
                        TextEntry::make('created_at')
                            ->state('Aangemaakt op')
                            ->dateTime(),
                        TextEntry::make('updated_at')
                            ->state('Laatst aangepast op')
                            ->dateTime(),
                        TextEntry::make('user_id')
                            ->state('Aangemaakt door')
                            ->getStateUsing(function ($record) {
                                return $record->user ? $record->user->name : 'Systeem';
                            }),
                        TextEntry::make('site_ids')
                            ->state('Actief op site(s)')
                            ->hidden(!(Sites::getAmountOfSites() > 1))
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
                Fieldset::make('Waarde')
                    ->schema([
                        TextEntry::make('discount_amount')
                            ->state('Huidige waarde')
                            ->money('EUR'),
                        TextEntry::make('initial_amount')
                            ->state('Initiele waarde')
                            ->money('EUR'),
                        TextEntry::make('reserved_amount')
                            ->state('Gereserveerde waarde')
                            ->helperText('Dit is de waarde die momenteel in gebruik is in openstaande bestellingen.')
                            ->money('EUR'),
                        TextEntry::make('used_amount')
                            ->state('Gebruikte waarde')
                            ->helperText('Dit is de waarde die al gebruikt is in afgeronde bestellingen.')
                            ->money('EUR'),
                    ]),
                Fieldset::make('Logboek')
                    ->schema(function ($record) {
                        $schema = [];

                        foreach ($record->logs as $log) {
                            $schema[] = Fieldset::make('Log van ' . $log->created_at->format('d-m-Y H:i'))
                                ->schema([
                                    TextEntry::make('tag_' . $log->id)
                                        ->state('Log')
                                        ->default($log->tag()),
                                    TextEntry::make('created_at_' . $log->id)
                                        ->state('Log aangemaakt op')
                                        ->dateTime()
                                        ->default($log->created_at),
                                    TextEntry::make('user_id_' . $log->id)
                                        ->state('Door')
                                        ->columnSpanFull()
                                        ->default($log->user ? $log->user->name : 'Systeem'),
                                    TextEntry::make('old_amount_' . $log->id)
                                        ->state('Oude waarde')
                                        ->money('EUR')
                                        ->default($log->old_amount),
                                    TextEntry::make('new_amount_' . $log->id)
                                        ->state('Nieuwe waarde')
                                        ->money('EUR')
                                        ->default($log->new_amount),
                                ])
                                ->columns(2);
                        }

                        return array_reverse($schema);
                    }),
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
            'index' => ListGiftcards::route('/'),
            'create' => CreateGiftcard::route('/create'),
            'edit' => EditGiftcard::route('/{record}/edit'),
            'view' => ViewGiftcard::route('/{record}/view'),
        ];
    }
}
