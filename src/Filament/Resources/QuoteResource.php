<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources;

use UnitEnum;
use BackedEnum;
use Filament\Tables\Table;
use Filament\Schemas\Schema;
use Filament\Actions\EditAction;
use Filament\Resources\Resource;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DatePicker;
use Filament\Schemas\Components\Section;
use Illuminate\Database\Eloquent\Model;
use Filament\Tables\Filters\SelectFilter;
use Dashed\DashedCore\Models\User;
use Dashed\DashedEcommerceCore\Models\Quote;
use Dashed\DashedEcommerceCore\Models\Product;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Dashed\DashedCore\Classes\Sites;
use Dashed\DashedCore\Classes\QueryHelpers\SearchQuery;
use Dashed\DashedCore\Classes\QueryHelpers\TokenizedSearch;
use Dashed\DashedEcommerceCore\Classes\VatDisplay;
use Dashed\DashedEcommerceCore\Services\Quotes\QuoteDefaults;
use Dashed\DashedCore\Classes\QueryHelpers\RelationshipSearchQuery;
use Dashed\DashedEcommerceCore\Filament\Resources\QuoteResource\Pages\EditQuote;
use Dashed\DashedEcommerceCore\Filament\Resources\QuoteResource\Pages\ListQuotes;
use Dashed\DashedEcommerceCore\Filament\Resources\QuoteResource\Pages\CreateQuote;

class QuoteResource extends Resource
{
    protected static ?string $model = Quote::class;

    protected static ?string $slug = 'quotes';

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-document-text';
    protected static string | UnitEnum | null $navigationGroup = 'E-commerce';
    protected static ?int $navigationSort = 2;

    public static function getNavigationLabel(): string
    {
        return __('Offertes');
    }

    public static function getModelLabel(): string
    {
        return __('Offerte');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Offertes');
    }

    /** Verstuurde offertes waar de klant nog niets mee gedaan heeft. */
    public static function getNavigationBadge(): ?string
    {
        $count = Quote::query()->open()->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    /**
     * Een verstuurde offerte is een uitgaand document en blijft staan; alleen
     * een concept mag weg.
     */
    public static function canDelete(Model $record): bool
    {
        return $record->status === Quote::STATUS_CONCEPT
            && parent::canDelete($record);
    }

    /**
     * Een verstuurde offerte staat vast. Het document dat de klant heeft is het
     * document, en de akkoord-PDF moet erop kunnen leunen; wie er iets aan wil
     * veranderen maakt een nieuwe revisie, die de oude versie vervangt.
     */
    public static function isLocked(?Model $record): bool
    {
        return $record instanceof Quote && $record->sent_at !== null;
    }

    public static function form(Schema $schema): Schema
    {
        // Untyped $record met opzet: Filament lost een parameter zonder type op
        // naam op, en dat geeft netjes null op de aanmaakpagina. Een ?Model-hint
        // laat hij daar door de container lopen en dat klapt op een abstracte
        // klasse.
        return $schema->disabled(fn ($record) => static::isLocked($record))->schema([
            Section::make(__('Klant'))
                ->columnSpanFull()
                ->columns(2)
                ->schema([
                    Select::make('user_id')
                        ->label(__('Klantaccount'))
                        ->searchable()
                        ->helperText(__('Leeg laten voor een prospect zonder account'))
                        ->getSearchResultsUsing(fn (string $search) => static::searchCustomers($search))
                        ->getOptionLabelUsing(fn ($value) => static::customerLabel(User::find($value)))
                        ->live()
                        ->afterStateUpdated(function ($state, Set $set) {
                            $user = $state ? User::find($state) : null;
                            if (! $user) {
                                return;
                            }
                            $set('first_name', $user->first_name);
                            $set('last_name', $user->last_name);
                            $set('email', $user->email);
                            $set('company_name', $user->company);
                            $set('btw_id', $user->tax_id);
                            $set('phone_number', $user->phone_number);

                            // Een account dat alleen een afleveradres heeft ingevuld
                            // hoort toch een factuuradres op de offerte te krijgen:
                            // het factuuradres is hier het adres dat altijd gevuld
                            // moet zijn, want de order op rekening leunt erop.
                            $set('invoice_street', $user->invoice_street ?: $user->street);
                            $set('invoice_house_nr', $user->invoice_house_nr ?: $user->house_nr);
                            $set('invoice_zip_code', $user->invoice_zip_code ?: $user->zip_code);
                            $set('invoice_city', $user->invoice_city ?: $user->city);
                            $set('invoice_country', $user->invoice_country ?: $user->country);

                            $set('street', $user->street);
                            $set('house_nr', $user->house_nr);
                            $set('zip_code', $user->zip_code);
                            $set('city', $user->city);
                            $set('country', $user->country);
                        }),
                    TextInput::make('company_name')->label(__('Bedrijfsnaam'))->maxLength(255),
                    TextInput::make('btw_id')->label(__('BTW-nummer'))->maxLength(50),
                    TextInput::make('first_name')->label(__('Voornaam'))->maxLength(255),
                    TextInput::make('last_name')->label(__('Achternaam'))->maxLength(255),
                    TextInput::make('email')->label(__('E-mailadres'))->email()->required()->maxLength(255),
                    TextInput::make('phone_number')->label(__('Telefoonnummer'))->maxLength(50),
                    TextInput::make('invoice_street')->label(__('Straat'))->maxLength(255),
                    TextInput::make('invoice_house_nr')->label(__('Huisnummer'))->maxLength(20),
                    TextInput::make('invoice_zip_code')->label(__('Postcode'))->maxLength(20),
                    TextInput::make('invoice_city')->label(__('Plaats'))->maxLength(255),
                    TextInput::make('invoice_country')->label(__('Land'))->maxLength(255),
                ]),

            Section::make(__('Afleveradres'))
                ->description(__('Leeg laten om het factuuradres te gebruiken'))
                ->columnSpanFull()
                ->columns(2)
                ->collapsed()
                ->schema([
                    TextInput::make('street')->label(__('Straat'))->maxLength(255),
                    TextInput::make('house_nr')->label(__('Huisnummer'))->maxLength(20),
                    TextInput::make('zip_code')->label(__('Postcode'))->maxLength(20),
                    TextInput::make('city')->label(__('Plaats'))->maxLength(255),
                    TextInput::make('country')->label(__('Land'))->maxLength(255),
                ]),

            Section::make(__('Offerte'))
                ->columnSpanFull()
                ->columns(2)
                ->schema([
                    TextInput::make('title')->label(__('Onderwerp'))->required()->maxLength(255)
                        ->helperText(__('Staat als titelregel boven de offerteregels')),
                    TextInput::make('reference')->label(__('Referentie'))->maxLength(255),
                    DatePicker::make('valid_until')
                        ->label(__('Geldig tot en met'))
                        ->required()
                        ->default(fn () => now()->addDays(QuoteDefaults::validityDays())),
                    Select::make('locale')
                        ->label(__('Taal'))
                        ->options(\Dashed\DashedCore\Classes\Locales::getLocalesArray())
                        ->default(app()->getLocale())
                        ->required(),
                    Toggle::make('prices_ex_vat')
                        ->label(__('Bedragen tonen exclusief btw'))
                        ->default(true),
                    Select::make('payment_route')
                        ->label(__('Na akkoord'))
                        ->options([
                            Quote::ROUTE_PREPAY => __('Vooraf betalen'),
                            Quote::ROUTE_ON_ACCOUNT => __('Op rekening'),
                        ])
                        ->default(Quote::ROUTE_PREPAY)
                        ->required(),
                ]),

            Section::make(__('Regels'))
                ->columnSpanFull()
                ->schema([
                    Repeater::make('lines')
                        ->label('')
                        ->relationship()
                        ->orderColumn('sort_order')
                        ->reorderableWithButtons()
                        ->collapsible()
                        ->addActionLabel(__('Regel toevoegen'))
                        ->defaultItems(1)
                        ->itemLabel(fn (array $state): ?string => $state['name'] ?? null)
                        ->schema(static::lineFields())
                        ->columns(2),
                ]),

            Section::make(__('Teksten'))
                ->columnSpanFull()
                ->schema([
                    Textarea::make('intro')->label(__('Intro'))->rows(3)->columnSpanFull()
                        ->default(fn (Get $get) => QuoteDefaults::text('intro', $get('locale') ?: app()->getLocale())),
                    Textarea::make('terms')->label(__('Planning en voorwaarden'))->rows(8)->columnSpanFull()
                        ->default(fn (Get $get) => QuoteDefaults::text('terms', $get('locale') ?: app()->getLocale())),
                    Textarea::make('acceptance_text')->label(__('Akkoordtekst'))->rows(3)->columnSpanFull()
                        ->default(fn (Get $get) => QuoteDefaults::text('acceptance', $get('locale') ?: app()->getLocale())),
                ]),

            Section::make(__('Intern'))
                ->columnSpanFull()
                ->collapsed()
                ->schema([
                    Textarea::make('notes')->label(__('Notitie'))->rows(3)->columnSpanFull()
                        ->helperText(__('Alleen voor intern gebruik, komt niet op de offerte')),
                ]),
        ]);
    }

    /**
     * De accountkiezer zoekt breed: op voornaam, achternaam, bedrijf en
     * e-mailadres, woord voor woord. User heeft geen scopeSearch, dus
     * RelationshipSearchQuery zou hier terugvallen op een LIKE op één kolom en
     * een achternaam nooit vinden. Zie "Breed zoeken" in CLAUDE.md; dezelfde
     * vorm als de accountkiezers op OrderResource, POSPage en ResellerResource.
     *
     * @return array<int, string>
     */
    public static function searchCustomers(string $search): array
    {
        return TokenizedSearch::apply(User::query(), $search, ['first_name', 'last_name', 'company', 'email'])
            ->limit(50)
            ->get()
            ->mapWithKeys(fn (User $user) => [$user->id => static::customerLabel($user)])
            ->all();
    }

    public static function customerLabel(?User $user): string
    {
        if (! $user) {
            return '';
        }

        $name = trim((string) $user->name);

        return trim(($name !== '' && $name !== $user->email ? $name.' ' : '').'('.$user->email.')');
    }

    /** De velden van een offerteregel; ook gebruikt door de revisie-actie. */
    public static function lineFields(): array
    {
        return [
            Select::make('product_id')
                ->label(__('Catalogusproduct'))
                ->searchable()
                ->helperText(__('Leeg laten voor een vrije regel'))
                ->getSearchResultsUsing(fn (string $search) => RelationshipSearchQuery::make(Product::class, $search))
                ->getOptionLabelUsing(fn ($value) => Product::find($value)?->nameWithParents)
                ->live()
                ->afterStateUpdated(function ($state, Set $set) {
                    $product = $state ? Product::find($state) : null;
                    if (! $product) {
                        return;
                    }
                    // Prijs en btw worden gekopieerd en daarna losgekoppeld: een
                    // prijswijziging in de webshop mag een verstuurde offerte niet
                    // stilletjes veranderen.
                    $set('name', $product->name);
                    $set('sku', $product->sku);
                    $set('vat_rate', $product->vat_rate ?? 21);
                    $set('unit_price', (float) $product->getRawOriginal('current_price'));
                }),
            TextInput::make('name')->label(__('Omschrijving'))->required()->maxLength(255),
            Textarea::make('description')->label(__('Toelichting'))->rows(3)->columnSpanFull(),
            TextInput::make('sku')->label(__('Artikelnummer'))->maxLength(255),
            TextInput::make('quantity')->label(__('Aantal'))->numeric()->minValue(1)->default(1)->required(),
            TextInput::make('vat_rate')->label(__('BTW-tarief'))->numeric()->default(21)->required()->suffix('%'),
            TextInput::make('unit_price')
                ->label(__('Prijs per stuk inclusief btw'))
                ->numeric()
                ->required()
                ->prefix('€')
                ->helperText(fn (Get $get) => __('Exclusief btw: :bedrag', [
                    'bedrag' => number_format(VatDisplay::exFromIncl((float) $get('unit_price'), (float) ($get('vat_rate') ?: 21)), 2, ',', '.'),
                ]))
                ->live(onBlur: true),
            Toggle::make('is_optional')->label(__('Optioneel, de klant kiest zelf'))->live(),
            Toggle::make('is_selected')
                ->label(__('Standaard aangevinkt'))
                ->default(true)
                ->visible(fn (Get $get) => (bool) $get('is_optional')),
            TextInput::make('choice_group')
                ->label(__('Keuzegroep'))
                ->maxLength(50)
                ->helperText(__('Regels met dezelfde groep zijn een keuze uit meerdere; leeg is een los vinkje'))
                ->visible(fn (Get $get) => (bool) $get('is_optional')),
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('quote_number')
                    ->label(__('Nummer'))
                    ->formatStateUsing(fn (Quote $record) => $record->displayNumber())
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->label(__('Klant'))
                    ->description(fn (Quote $record) => $record->company_name)
                    ->searchable(query: SearchQuery::make()),
                TextColumn::make('title')->label(__('Onderwerp'))->limit(40)->searchable(),
                TextColumn::make('total')->label(__('Totaal'))->money('eur')->sortable(),
                TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge()
                    ->formatStateUsing(fn (Quote $record) => $record->statusLabel())
                    ->color(fn (Quote $record) => match ($record->status) {
                        Quote::STATUS_ACCEPTED => 'success',
                        Quote::STATUS_REJECTED, Quote::STATUS_EXPIRED => 'danger',
                        Quote::STATUS_SENT => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('valid_until')
                    ->label(__('Geldig tot'))
                    ->date('d-m-Y')
                    ->color(fn (Quote $record) => $record->isExpired() ? 'danger' : null)
                    ->sortable(),
                TextColumn::make('sent_at')->label(__('Verstuurd'))->dateTime('d-m-Y H:i')->sortable()->toggleable(),
                TextColumn::make('viewed_at')
                    ->label(__('Geopend'))
                    ->dateTime('d-m-Y H:i')
                    ->placeholder('-')
                    ->toggleable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label(__('Status'))
                    ->multiple()
                    ->options(Quote::statusLabels())
                    ->default([Quote::STATUS_CONCEPT, Quote::STATUS_SENT]),
                SelectFilter::make('site_id')
                    ->label(__('Site'))
                    ->options(fn (): array => collect(Sites::getSites())->pluck('name', 'id')->all())
                    ->visible(Sites::getAmountOfSites() > 1),
            ])
            ->recordActions([
                EditAction::make()->button(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListQuotes::route('/'),
            'create' => CreateQuote::route('/create'),
            'edit' => EditQuote::route('/{record}/edit'),
        ];
    }
}
