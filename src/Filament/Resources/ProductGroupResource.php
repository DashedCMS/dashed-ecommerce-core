<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources;

use UnitEnum;
use BackedEnum;
use Filament\Tables\Table;
use Filament\Actions\Action;
use Filament\Schemas\Schema;
use Dashed\DashedAi\Facades\Ai;
use Filament\Actions\EditAction;
use Filament\Resources\Resource;
use Filament\Actions\DeleteAction;
use Illuminate\Support\Facades\DB;
use Filament\Actions\RestoreAction;
use Filament\Tables\Filters\Filter;
use Dashed\DashedCore\Classes\Sites;
use Filament\Support\Icons\Heroicon;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Dashed\DashedCore\Classes\Locales;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Tables\Columns\ImageColumn;
use Filament\Forms\Components\FileUpload;
use Filament\Tables\Filters\TrashedFilter;
use Dashed\DashedAi\Jobs\GenerateAiContent;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Forms\Components\CheckboxList;
use Dashed\DashedEcommerceCore\Models\Product;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Dashed\DashedEcommerceCore\Models\ProductTab;
use Dashed\DashedTranslations\Models\Translation;
use Dashed\DashedEcommerceCore\Models\ProductExtra;
use Dashed\DashedEcommerceCore\Models\ProductGroup;
use Filament\Forms\Components\Repeater\TableColumn;
use Dashed\DashedEcommerceCore\Models\ProductFilter;
use Dashed\DashedEcommerceCore\Models\ProductCategory;
use Dashed\DashedCore\Classes\QueryHelpers\SearchQuery;
use Dashed\DashedCore\Filament\Concerns\HasVisitableTab;
use Dashed\DashedEcommerceCore\Models\ProductFilterOption;
use Dashed\DashedCore\Filament\Concerns\HasCustomBlocksTab;
use Dashed\DashedEcommerceCore\Models\ProductCharacteristics;
use LaraZeus\SpatieTranslatable\Resources\Concerns\Translatable;
use Dashed\DashedCore\Classes\Actions\ActionGroups\ToolbarActions;
use Dashed\DashedCore\Classes\QueryHelpers\RelationshipSearchQuery;
use Dashed\DashedEcommerceCore\Jobs\CreateMissingProductVariationsJob;
use Dashed\DashedEcommerceCore\Filament\Resources\ProductGroupResource\Pages\EditProductGroup;
use Dashed\DashedEcommerceCore\Filament\Resources\ProductGroupResource\Pages\ListProductGroups;
use Dashed\DashedEcommerceCore\Filament\Resources\ProductGroupResource\Pages\CreateProductGroup;
use Dashed\DashedEcommerceCore\Filament\Resources\ProductGroupResource\RelationManagers\ProductsRelationManager;

class ProductGroupResource extends Resource
{
    use Translatable;
    use HasVisitableTab;
    use HasCustomBlocksTab;

    protected static ?string $model = ProductGroup::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static string|BackedEnum|null $navigatioonIcon = 'heroicon-o-shopping-bag';
    protected static string|UnitEnum|null $navigationGroup = 'Producten';
    protected static ?string $navigationLabel = 'Product groepen';
    protected static ?string $label = 'Product groep';
    protected static ?string $pluralLabel = 'Product groepen';
    protected static ?int $navigationSort = 1;

    public static function getGloballySearchableAttributes(): array
    {
        return [
            'site_ids',
            'name',
            'slug',
            'short_description',
            'description',
            'search_terms',
            'content',
        ];
    }

    public static function form(Schema $schema): Schema
    {
        config(['filament-tiptap-editor.directory' => 'dashed/products/images']);

        $newSchema = [];

        $newSchema[] = Section::make(__('Algemene instellingen'))->columnSpanFull()
            ->schema([
                Select::make('site_ids')
                    ->multiple()
                    ->label(__('Actief op sites'))
                    ->options(collect(Sites::getSites())->pluck('name', 'id')->toArray())
                    ->default([Sites::getFirstSite()['id']])
                    ->hidden(fn (Get $get) => ! (Sites::getAmountOfSites() > 1))
                    ->required(),
                Toggle::make('public')
                    ->label(__('Openbaar'))
                    ->default(true)
                    ->helperText(__('Als je deze op NIET openbaar zet, worden alle variaties verborgen')),
                Toggle::make('showable_in_index')
                    ->label(__('Tonen in index'))
                    ->default(true)
                    ->helperText(__('Indien je deze uitzet, worden de variaties niet getoond op overzichtspagina\'s maar zijn ze nog wel zichtbaar via directe link.')),
                Toggle::make('only_show_parent_product')
                    ->label(__('Toon 1 variatie op overzichtspagina')),
                Toggle::make('sync_categories_to_products')
                    ->label(__('Synchroniseer categorieën naar producten'))
                    ->default(1)
                    ->reactive(),
                Toggle::make('use_parent_stock')
                    ->label(__('Gebruik voorraad informatie van deze product groep'))
                    ->helperText(__('Let op: dit is slechts een extra check, de voorraad van het variaties gelden ook'))
                    ->default(0)
                    ->reactive(),
                Select::make('first_selected_product_id')
                    ->label(__('Eerste geselecteerde product'))
//                    ->relationship('firstSelectedProduct', 'name')
                    ->options(fn ($record) => $record ? $record->products->pluck('name', 'id') : [])
                    ->preload()
                    ->searchable()
                    ->helperText(__('Indien je een product selecteert, wordt deze standaard geselecteerd op de product groep pagina')),

//                Select::make('copyable_to_childs') //Todo: this should be done automaticly now
//                    ->label('Welke onderdelen moeten gekopieerd worden naar alle variaties?')
//                    ->multiple()
//                    ->searchable()
//                    ->preload()
//                    ->helperText('Let op: dit OVERSCHRIJFT de huidige waardes van de variaties')
//                    ->options([
//                        'images' => 'Afbeeldingen',
//                        'productCategories' => 'Product categorieën',
//                        'shippingClasses' => 'Verzendklasses',
//                        'suggestedProducts' => 'Voorgestelde producten',
//                        'crossSellProducts' => 'Cross sell producten',
//                        'content' => 'Content',
//                        'description' => 'Uitgebreide beschrijving',
//                        'short_description' => 'Korte beschrijving',
//                        'customBlocks' => 'Maatwerk blokken',
//                    ]),
            ])
            ->columns(2)
            ->collapsible()
            ->persistCollapsed();

        $productFilters = ProductFilter::with(['productFilterOptions'])->get();
        $productFilterSchema = [];

        $productFilterSchema[] = Select::make('productFilters')
            ->multiple()
            ->label(__('Actieve filters'))
            ->options($productFilters->pluck('name', 'id')->toArray())
            ->reactive()
            ->columnSpanFull()
            ->searchable();

        foreach ($productFilters as $productFilter) {
            $productFiltersSchema = [];

            $productFiltersSchema[] = Toggle::make("product_filter_{$productFilter->id}_use_for_variations")
                ->label(__(':naam gebruiken voor variaties op de product pagina', ['naam' => $productFilter->name]))
                ->reactive();

            $productFiltersSchema[] = Select::make("product_filter_options_{$productFilter->id}")
                ->label(__('Filter opties'))
                ->multiple()
                ->hintAction(
                    Action::make('addAllFilters')
                        ->label(__('Voeg alle opties toe'))
                        ->icon('heroicon-o-plus')
                        ->action(function (Set $set, $livewire) use ($productFilter) {
                            $set("product_filter_options_{$productFilter->id}", $productFilter->productFilterOptions->pluck('id')->toArray());
                            Notification::make()
                                ->title(__('Alle opties zijn toegevoegd'))
                                ->success()
                                ->send();
                        })
                )
                ->options($productFilter->productFilterOptions->pluck('name', 'id')->toArray())
                ->preload()
                ->reactive()
                ->columnSpanFull()
                ->searchable();

            $productFilterSchema[] = Section::make(__('Filter opties voor :naam', ['naam' => $productFilter->name]))->columnSpanFull()
                ->schema($productFiltersSchema)
                ->collapsible()
                ->persistCollapsed()
                ->columns(2)
                ->visible(fn (Get $get) => in_array($productFilter->id, $get('productFilters')));
        }
        //
        $newSchema[] = Section::make(__('Filters beheren'))->columnSpanFull()
            ->headerActions([
                \Filament\Actions\Action::make('createMissingVariations')
                    ->label(fn ($record) => __('Ontbrekende variaties aanmaken (:aantal)', ['aantal' => count($record->missing_variations ?? [])]))
                    ->visible(fn ($livewire, $record) => count($record->missing_variations ?? []) && $livewire instanceof EditProductGroup)
                    ->modalHeading(__('Ontbrekende variaties aanmaken'))
                    ->modalDescription(__('Vink de variaties uit die je NIET wilt aanmaken. Alleen de aangevinkte variaties worden aangemaakt.'))
                    ->modalSubmitActionLabel(__('Geselecteerde aanmaken'))
                    ->fillForm(fn ($record) => ['variations' => array_keys(static::missingVariationOptions($record))])
                    ->form(fn ($record) => [
                        CheckboxList::make('variations')
                            ->label(__('Aan te maken variaties'))
                            ->options(static::missingVariationOptions($record))
                            ->columns(2)
                            ->bulkToggleable(),
                        Toggle::make('exclude_unchecked')
                            ->label(__('Niet-aangevinkte variaties uitsluiten van toekomstige voorstellen'))
                            ->helperText(__('Uitgesloten variaties tellen niet meer mee en staan niet meer in deze lijst. Terugzetten kan via de knop "Uitgesloten variaties".'))
                            ->default(false),
                    ])
                    ->action(function (array $data, $record) {
                        $allKeys = array_keys(static::missingVariationOptions($record));
                        $checkedKeys = array_values(array_intersect($allKeys, $data['variations'] ?? []));
                        $variations = static::variationsFromKeys($checkedKeys);

                        $excluded = 0;
                        if ($data['exclude_unchecked'] ?? false) {
                            $toExclude = static::variationsFromKeys(array_values(array_diff($allKeys, $checkedKeys)));
                            $excluded = count($toExclude);
                            if ($excluded) {
                                $record->excludeVariations($toExclude);
                            }
                        }

                        if (! count($variations) && ! $excluded) {
                            Notification::make()
                                ->title(__('Geen variaties geselecteerd'))
                                ->warning()
                                ->send();

                            return;
                        }

                        if (count($variations)) {
                            CreateMissingProductVariationsJob::dispatch($record, $variations)
                                ->onQueue('ecommerce');
                        }

                        $title = count($variations)
                            ? __(':aantal variatie(s) worden aangemaakt, refresh de pagina om de voortgang te zien', ['aantal' => count($variations)])
                            : __(':aantal variatie(s) uitgesloten', ['aantal' => $excluded]);

                        Notification::make()
                            ->title($title)
                            ->body(count($variations) && $excluded ? __(':aantal variatie(s) uitgesloten', ['aantal' => $excluded]) : null)
                            ->success()
                            ->send();
                    }),
                \Filament\Actions\Action::make('restoreExcludedVariations')
                    ->label(fn ($record) => __('Uitgesloten variaties (:aantal)', ['aantal' => count($record->excludedVariations())]))
                    ->color('gray')
                    ->visible(fn ($livewire, $record) => count($record->excludedVariations()) && $livewire instanceof EditProductGroup)
                    ->modalHeading(__('Uitgesloten variaties'))
                    ->modalDescription(__('Deze variaties worden niet meer voorgesteld. Vink aan welke je weer wilt laten voorstellen.'))
                    ->modalSubmitActionLabel(__('Geselecteerde terugzetten'))
                    ->form(fn ($record) => [
                        CheckboxList::make('variations')
                            ->label(__('Terug te zetten variaties'))
                            ->options(static::excludedVariationOptions($record))
                            ->columns(2)
                            ->bulkToggleable(),
                    ])
                    ->action(function (array $data, $record) {
                        $variations = static::variationsFromKeys($data['variations'] ?? []);

                        if (! count($variations)) {
                            Notification::make()
                                ->title(__('Geen variaties geselecteerd'))
                                ->warning()
                                ->send();

                            return;
                        }

                        $record->restoreVariations($variations);

                        Notification::make()
                            ->title(__(':aantal variatie(s) worden weer voorgesteld', ['aantal' => count($variations)]))
                            ->success()
                            ->send();
                    }),
            ])
            ->schema($productFilterSchema)
            ->columns(2)
            ->collapsible()
            ->persistCollapsed();

        $productCharacteristicsTableColumns = [
            TableColumn::make('Kenmerk'),
        ];

        $productCharacteristics = ProductCharacteristics::orderBy('order', 'ASC')->get();

        $productCharacteristicsSchema = [
            Select::make('product_characteristic_id')
                ->label(__('Kenmerk'))
                ->options($productCharacteristics->pluck('name', 'id')->toArray())
                ->searchable()
                ->required(),
        ];
        foreach (Locales::getLocales() as $locale) {
            $productCharacteristicsTableColumns[] = TableColumn::make("Waarde ({$locale['id']})");
            $productCharacteristicsSchema[] = TextInput::make('value_' . $locale['id']);
        }

        $newSchema[] = Section::make(__('Kenmerken beheren'))->columnSpanFull()
            ->schema([
                Repeater::make('productCharacteristics')
                    ->label(__('Kenmerken'))
                    ->relationship('productCharacteristics')
                    ->table($productCharacteristicsTableColumns)
                    ->mutateRelationshipDataBeforeFillUsing(function (array $data, $livewire): array {
                        foreach (Locales::getLocales() as $locale) {
                            $data['value_' . $locale['id']] = json_decode(DB::table('dashed__product_characteristic')->where('id', $data['id'])->first()->value, true)[$locale['id']] ?? '';
                        }

                        return $data;
                    })
                    ->saveRelationshipsUsing(function (array $state, $livewire, $record) {
                        $entryIds = [];

                        foreach ($state as $entry) {
                            if ($entry['id'] ?? false) {
                                $characteristic = $record->productCharacteristics()->where('id', $entry['id'])->first();
                            } else {
                                $characteristic = $record->productCharacteristics()->create([
                                    'product_characteristic_id' => $entry['product_characteristic_id'],
                                    'product_group_id' => $record->id,
                                    'value' => '',
                                ]);
                            }

                            foreach (Locales::getLocales() as $locale) {
                                $characteristic->setTranslation('value', $locale['id'], $entry['value_' . $locale['id']]);
                            }
                            $characteristic->save();
                            $entryIds[] = $characteristic->id;
                        }

                        $record->productCharacteristics()->whereNotIn('id', $entryIds)->delete();
                    })
                    ->columnSpanFull()
                    ->schema($productCharacteristicsSchema),
            ])
            ->columns([
                'default' => 1,
                'lg' => 3,
            ])
            ->persistCollapsed()
            ->collapsed()
            ->hidden(fn ($livewire, Get $get, $record) => $livewire instanceof CreateProductGroup || ($get('type') == 'variable' && (! $record && ! $get('parent_id') || $record && ! $record->parent_id)));

        $newSchema[] = Section::make(__('Content beheren'))
            ->columnSpanFull()
            ->schema(array_merge([
                TextInput::make('name')
                    ->label(__('Naam'))
                    ->maxLength(255)
                    ->required(),
                TextInput::make('slug')
                    ->label(__('Slug'))
                    ->unique('dashed__product_groups', 'slug', fn ($record) => $record)
                    ->helperText(__('Laat leeg om automatisch te laten genereren')),
                cms()->editorField('description', 'Uitgebreide beschrijving')
                    ->hintAction(
                        Action::make('generateDescription')
                            ->label(__('Genereer beschrijving'))
                            ->icon(Heroicon::PencilSquare)
                            ->schema([
                                Textarea::make('description')
                                    ->label(__('Beschrijving'))
                                    ->rows(7)
                                    ->required()
                                    ->helperText(__('Beschrijf hierin het product en bijvoorbeeld een voorbeeld beschrijving. De standaard prompt kan je aanpassen in vertalingen.')),
                            ])
                            ->fillForm(function ($record) {
                                return [
                                    'description' => Translation::get('product_description_prompt', 'product', 'Schrijf een uitgebreide product beschrijving voor het volgende product: :name:. Dit is de link van het product: :url:. Zorg dat de beschrijving aantrekkelijk is en de voordelen benoemd voor de klant. Je mag gebruikmaken van HTML voor bijvoorbeeld Bold tekst. Schrijf in een vlotte en overtuigende stijl. Vermeld ook de categorie waarin het product valt: :categoryName:. Gebruik maximaal 3000 tekens. Een voorbeeld beschrijving hoe wij het wensen is als volgt: naam met categorie, beschrijving, opsomming van kenmerken.', 'textarea', [
                                        'name' => $record->name,
                                        'url' => url($record->getUrl()),
                                        'categoryName' => $record->productCategories->first() ? $record->productCategories->first()->nameWithParents : 'Onbekend',
                                    ]),
                                ];
                            })
                            ->visible(fn ($record) => $record && class_exists(Ai::class) && Ai::hasProvider())
                            ->action(function ($data, Set $set, $record, $livewire) {
                                $description = $data['description'] ?? '';

                                GenerateAiContent::dispatch($record, 'description', $description, $livewire->activeLocale);

                                Notification::make()
                                    ->title(__('De beschrijving wordt gegenereerd. Refresh de pagina om de nieuwe beschrijving te zien.'))
                                    ->success()
                                    ->send();
                            })
                    )
                    ->helperText(__('Mogelijke variablen: :name:, :categorie naam:'))
                    ->rules([
                        'max:10000',
                    ])
                    ->columnSpan([
                        'default' => 1,
                        'lg' => 2,
                    ]),
                Textarea::make('short_description')
                    ->label(__('Korte beschrijving'))
                    ->helperText(__('Mogelijke variablen: :name:, :categorie naam:'))
                    ->rows(5)
                    ->maxLength(2500),
                Textarea::make('search_terms')
                    ->label(__('Zoekwoorden'))
                    ->rows(2)
                    ->helperText(__('Vul hier termen in waar het product nog meer op gevonden moet kunnen worden. Deze termen gelden voor alle varianten.'))
                    ->maxLength(2500),
                TextInput::make('order')
                    ->label(__('Volgorde'))
                    ->required()
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(100000)
                    ->default(1),
                FileUpload::make('new_images')
                    ->label(__('Nieuwe afbeeldingen'))
                    ->visible(fn ($livewire) => $livewire instanceof EditProductGroup)
                    ->helperText(__('Deze afbeeldingen worden toegevoegd aan de product groep en achter de rest van de afbeeldingen geplaatst. Deze worden opgeslagen in de map: producten'))
                    ->image()
                    ->preserveFilenames()
                    ->multiple()
                    ->columnSpanFull(),
                mediaHelper()->field('images', 'Afbeeldingen', required: false, multiple: true, defaultFolder: 'producten')
                    ->columnSpanFull()
                    ->helperText(__('Afbeeldingen van een variant worden VOOR de afbeelding van de product groep getoond')),
                cms()->getFilamentBuilderBlock(),
            ], static::customBlocksTab(['productBlocks', 'productGroupBlocks'])))
            ->collapsible()
            ->persistCollapsed()
            ->columns([
                'default' => 1,
                'lg' => 2,
            ]);

        $newSchema[] = Section::make(__('Linkjes beheren'))
            ->columnSpanFull()
            ->schema([
                Select::make('productCategories')
                    ->multiple()
                    ->relationship('productCategories', 'name')
                    ->getSearchResultsUsing(fn ($search) => RelationshipSearchQuery::make(ProductCategory::class, $search))
                    ->searchable()
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->nameWithParents)
                    ->formatStateUsing(function ($state) {
                        return array_unique($state ?? []);
                    })
                    ->label(__('Link aan categorieeën'))
                    ->helperText(__('Bovenliggende categorieën worden automatisch geactiveerd. Deze categorieen gelden voor alle varianten.')),
                Select::make('suggestedProducts')
                    ->multiple()
                    ->relationship('suggestedProducts', 'name')
                    ->getSearchResultsUsing(fn ($search) => RelationshipSearchQuery::make(Product::class, $search))
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->nameWithParents)
                    ->helperText(__('Indien je bij een variant ook voorgestelde producten koppelt, worden deze samengevoegd'))
                    ->label(__('Link voorgestelde producten')),
                Select::make('suggestedProductGroups')
                    ->multiple()
                    ->relationship('suggestedProductGroups', 'name')
                    ->searchable()
                    ->preload()
                    ->label(__('Link voorgestelde productgroepen')),
                Select::make('crossSellProducts')
                    ->multiple()
                    ->relationship('crossSellProducts', 'name')
                    ->getSearchResultsUsing(fn ($search) => RelationshipSearchQuery::make(Product::class, $search))
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->nameWithParents)
                    ->label(__('Link cross sell producten'))
                    ->helperText(__('Dit mogen alleen maar producten zijn die zonder verplichte opties zijn. Indien je bij een variant ook cross sell producten koppelt, worden deze samengevoegd')),
                Select::make('crossSellProductGroups')
                    ->multiple()
                    ->relationship('crossSellProductGroups', 'name')
                    ->searchable()
                    ->preload()
                    ->label(__('Link cross sell productgroepen'))
                    ->helperText(__('Een groep met meerdere varianten toont op de productpagina een popup om de juiste variant te kiezen')),
                Select::make('globalProductExtras')
                    ->multiple()
                    ->preload()
                    ->relationship('globalProductExtras', 'name')
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->name)
                    ->getSearchResultsUsing(fn ($search, $query) => RelationshipSearchQuery::make(ProductExtra::class, $search, applyScopes: 'isGlobal'))
                    ->helperText(__('Indien je bij een variant ook product extras koppelt, worden deze samengevoegd'))
                    ->label(__('Link globale product extras')),
                Select::make('globalProductTabs')
                    ->multiple()
                    ->getSearchResultsUsing(fn ($search, $query) => RelationshipSearchQuery::make(ProductTab::class, $search, applyScopes: 'isGlobal'))
                    ->preload()
                    ->relationship('globalTabs', 'name')
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->name)
                    ->helperText(__('Indien je bij een variant ook product tabs koppelt, worden deze samengevoegd'))
                    ->label(__('Link globale product tabs')),
            ])
            ->columns([
                'default' => 1,
                'lg' => 2,
            ])
            ->persistCollapsed()
            ->collapsible();

        $newSchema[] = Section::make(__('Product extras'))->columnSpanFull()
            ->schema([
                Repeater::make('productExtras')
                    ->relationship('productExtras')
                    ->columns(2)
                    ->cloneable()
                    ->schema(array_merge(ProductExtra::getFilamentFields(), static::customBlocksTab('productExtraOptionBlocks'))),
            ])
            ->hidden(fn ($livewire) => $livewire instanceof CreateProductGroup)
            ->collapsible()
            ->persistCollapsed();

        $newSchema[] = Section::make(__('Product tabs'))->columnSpanFull()
            ->schema([
                Repeater::make('tabs')
                    ->label(__('Tabs'))
                    ->relationship('ownTabs')
                    ->cloneable()
                    ->schema([
                        TextInput::make('name')
                            ->label(__('Naam'))
                            ->required()
                            ->maxLength(100),
                        cms()->editorField('content', 'Content')
                            ->required(),
                    ]),
            ])
            ->hidden(fn ($livewire) => $livewire instanceof CreateProductGroup)
            ->collapsible()
            ->persistCollapsed();

        $newSchema[] = Section::make(__('Volume korting'))
            ->columnSpanFull()
            ->schema([
                Repeater::make('volumeDiscounts')
                    ->relationship('volumeDiscounts')
                    ->label(__('Volume korting'))
                    ->cloneable()
                    ->reorderable()
                    ->columnSpanFull()
                    ->schema([
                        Select::make('type')
                            ->label(__('Type'))
                            ->options([
                                'percentage' => __('Percentage'),
                                'fixed' => __('Vast bedrag'),
                            ])
                            ->default('percentage')
                            ->required()
                            ->reactive(),
                        TextInput::make('min_quantity')
                            ->label(__('Vanaf aantal'))
                            ->required()
                            ->default(5),
                        TextInput::make('discount_price')
                            ->label(__('Kortings prijs'))
                            ->numeric()
                            ->required()
                            ->visible(fn (Get $get) => $get('type') == 'fixed')
                            ->prefix('€'),
                        TextInput::make('discount_percentage')
                            ->label(__('Kortings percentage'))
                            ->numeric()
                            ->required()
                            ->visible(fn (Get $get) => $get('type') == 'percentage')
                            ->suffix('%'),
                        Toggle::make('active_for_all_variants')
                            ->label(__('Actief voor alle varianten'))
                            ->default(true)
                            ->reactive(),
                        Select::make('products')
                            ->multiple()
                            ->preload()
                            ->relationship('products', 'name')
                            ->options(function ($livewire) {
                                return Product::where('product_group_id', $livewire->record->id ?? 0)->pluck('name', 'id');
                            })
                            ->columnSpanFull()
                            ->getOptionLabelFromRecordUsing(fn ($record) => $record->nameWithParents)
                            ->label(__('Korting alleen voor deze producten'))
                            ->visible(fn (Get $get) => ! $get('active_for_all_variants'))
                            ->required(),
                    ])
                    ->columns([
                        'default' => 1,
                        'lg' => 2,
                    ]),
            ])
            ->columns([
                'default' => 1,
                'lg' => 2,
            ])
            ->hidden(fn ($livewire) => $livewire instanceof CreateProductGroup)
            ->persistCollapsed()
            ->collapsible();

        $newSchema[] = Section::make(__('Meta data'))->columnSpanFull()
            ->schema(static::metadataTab())
            ->collapsible()
            ->persistCollapsed();

        return $schema->schema($newSchema);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns(array_merge([
                ImageColumn::make('image')
                    ->getStateUsing(function ($record, $livewire) {
                        static $preloaded = false;
                        if (! $preloaded) {
                            $preloaded = true;

                            try {
                                $imageIds = $livewire->getTableRecords()->map(fn ($r) => $r->firstImage)->filter()->values()->all();
                                if ($imageIds) {
                                    mediaHelper()->preloadMediaUrls($imageIds, 'original');
                                }
                            } catch (\Throwable $e) {
                            }
                        }

                        return $record->firstImage ? (mediaHelper()->getSingleMedia($record->firstImage, 'original')->url ?? '') : null;
                    })
                    ->label(''),
                TextColumn::make('name')
                    ->label(__('Naam'))
                    ->searchable(query: SearchQuery::make())
                    ->sortable(),
                TextColumn::make('total_purchases')
//                    ->sum('products', 'purchases')
                    ->label(__('Aantal verkopen'))
                    ->sortable(),
                TextColumn::make('products_count')
                    ->counts('products')
                    ->label(__('Aantal producten'))
                    ->sortable(),
                TextColumn::make('products_sum_stock')
                    ->label(__('Totale voorraad'))
                    ->sum('products', 'stock')
                    ->sortable(),
            ], static::visitableTableColumns()))
            ->reorderable('order')
            ->recordActions([
                EditAction::make()
                    ->button(),
                DeleteAction::make(),
                RestoreAction::make(),
                ForceDeleteAction::make(),
            ])
            ->toolbarActions(ToolbarActions::getActions([
                RestoreBulkAction::make(),
                ForceDeleteBulkAction::make(),
            ]))
            ->filters([
                TrashedFilter::make(),
                Filter::make('categories')
                    ->schema([
                        Select::make('categories')
                            ->multiple()
                            ->label(__('Categorieen'))
                            ->options(ProductCategory::all()->pluck('name', 'id')),
                    ])
                    ->query(function (\Illuminate\Database\Eloquent\Builder $query, array $data): \Illuminate\Database\Eloquent\Builder {
                        if (! $data['categories']) {
                            return $query;
                        }

                        return $query->whereHas('productCategories', fn (\Illuminate\Database\Eloquent\Builder $query) => $query->whereIn('product_category_id', $data['categories']));
                    }),
            ])
            ->deferFilters(false);
    }

    public static function getRelations(): array
    {
        return [
            ProductsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProductGroups::route('/'),
            'create' => CreateProductGroup::route('/create'),
            'edit' => EditProductGroup::route('/{record}/edit'),
        ];
    }

    /**
     * Bouwt voor elke ontbrekende variatie een [key => label]-paar voor de
     * aanvinklijst. De key is de aaneengeschakelde product_filter_option_id's
     * (stabiel), het label is leesbaar, bv. "Maat: L · Kleur: Rood".
     *
     * @return array<string, string>
     */
    public static function missingVariationOptions($record): array
    {
        return static::variationOptions($record->missingVariations());
    }

    /**
     * Zelfde vorm als missingVariationOptions(), maar voor de uitgesloten
     * combinaties, zodat de terugzet-modal dezelfde leesbare lijst toont.
     *
     * @return array<string, string>
     */
    public static function excludedVariationOptions($record): array
    {
        return static::variationOptions($record->excludedVariations());
    }

    /**
     * Zet een vinklijst-key ("12-34") terug om naar de optie-id's.
     *
     * @param  array<int, string>  $keys
     * @return array<int, array<int, int>>
     */
    public static function variationsFromKeys(array $keys): array
    {
        return collect($keys)
            ->map(fn ($key) => array_values(array_filter(array_map('intval', explode('-', (string) $key)))))
            ->filter(fn ($ids) => count($ids))
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array<int, int>>  $variations
     * @return array<string, string>
     */
    private static function variationOptions(array $variations): array
    {
        $allOptionIds = collect($variations)
            ->flatMap(fn ($variation) => array_values($variation))
            ->unique()
            ->all();

        $optionsMap = ProductFilterOption::with('productFilter')
            ->whereIn('id', $allOptionIds)
            ->get()
            ->keyBy('id');

        $result = [];
        foreach ($variations as $variation) {
            $optionIds = array_values($variation);
            $key = implode('-', $optionIds);
            $label = collect($optionIds)
                ->map(function ($id) use ($optionsMap) {
                    $option = $optionsMap->get($id);
                    if (! $option) {
                        return (string) $id;
                    }
                    $filterName = $option->productFilter?->name;

                    return ($filterName ? $filterName . ': ' : '') . $option->name;
                })
                ->implode(' · ');
            $result[$key] = $label;
        }

        return $result;
    }
}
