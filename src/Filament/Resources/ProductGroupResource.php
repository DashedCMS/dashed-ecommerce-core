<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources;

use Dashed\DashedCore\Classes\OpenAIHelper;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedTranslations\Models\Translation;
use Filament\Support\Icons\Heroicon;
use Mockery\Matcher\Not;
use UnitEnum;
use BackedEnum;
use Filament\Tables\Table;
use Filament\Actions\Action;
use Filament\Schemas\Schema;
use Filament\Actions\EditAction;
use Filament\Resources\Resource;
use Filament\Actions\DeleteAction;
use Illuminate\Support\Facades\DB;
use Filament\Tables\Filters\Filter;
use Dashed\DashedCore\Classes\Sites;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Dashed\DashedCore\Classes\Locales;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Tables\Columns\ImageColumn;
use Filament\Forms\Components\FileUpload;
use Dashed\DashedEcommerceCore\Models\Product;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Dashed\DashedEcommerceCore\Models\ProductTab;
use Dashed\DashedEcommerceCore\Models\ProductExtra;
use Dashed\DashedEcommerceCore\Models\ProductGroup;
use Filament\Forms\Components\Repeater\TableColumn;
use Dashed\DashedEcommerceCore\Models\ProductFilter;
use Dashed\DashedEcommerceCore\Models\ProductCategory;
use Dashed\DashedCore\Classes\QueryHelpers\SearchQuery;
use Dashed\DashedCore\Filament\Concerns\HasVisitableTab;
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

        $newSchema[] = Section::make('Algemene instellingen')->columnSpanFull()
            ->schema([
                Select::make('site_ids')
                    ->multiple()
                    ->label('Actief op sites')
                    ->options(collect(Sites::getSites())->pluck('name', 'id')->toArray())
                    ->default([Sites::getFirstSite()['id']])
                    ->hidden(fn(Get $get) => !(Sites::getAmountOfSites() > 1))
                    ->required(),
                Toggle::make('public')
                    ->label('Openbaar')
                    ->default(true)
                    ->helperText('Als je deze op NIET openbaar zet, worden alle variaties verborgen'),
                Toggle::make('only_show_parent_product')
                    ->label('Toon 1 variatie op overzichtspagina'),
                Toggle::make('sync_categories_to_products')
                    ->label('Synchroniseer categorieën naar producten')
                    ->default(1)
                    ->reactive(),
                Toggle::make('use_parent_stock')
                    ->label('Gebruik voorraad informatie van deze product groep')
                    ->helperText('Let op: dit is slechts een extra check, de voorraad van het variaties gelden ook')
                    ->default(0)
                    ->reactive(),
                Select::make('first_selected_product_id')
                    ->label('Eerste geselecteerde product')
                    ->relationship('firstSelectedProduct', 'name')
                    ->options(fn($record) => $record ? $record->products->pluck('name', 'id') : [])
                    ->preload()
                    ->searchable()
                    ->helperText('Indien je een product selecteert, wordt deze standaard geselecteerd op de product groep pagina'),

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
            ->label('Actieve filters')
            ->options($productFilters->pluck('name', 'id')->toArray())
            ->reactive()
            ->columnSpanFull()
            ->searchable();

        foreach ($productFilters as $productFilter) {
            $productFiltersSchema = [];

            $productFiltersSchema[] = Toggle::make("product_filter_{$productFilter->id}_use_for_variations")
                ->label("$productFilter->name gebruiken voor variaties op de product pagina")
                ->reactive();

            $productFiltersSchema[] = Select::make("product_filter_options_{$productFilter->id}")
                ->label('Filter opties')
                ->multiple()
                ->hintAction(
                    Action::make('addAllFilters')
                        ->label('Voeg alle opties toe')
                        ->icon('heroicon-o-plus')
                        ->action(function (Set $set, $livewire) use ($productFilter) {
                            $set("product_filter_options_{$productFilter->id}", $productFilter->productFilterOptions->pluck('id')->toArray());
                            Notification::make()
                                ->title('Alle opties zijn toegevoegd')
                                ->success()
                                ->send();
                        })
                )
                ->options($productFilter->productFilterOptions->pluck('name', 'id')->toArray())
                ->preload()
                ->reactive()
                ->columnSpanFull()
                ->searchable();

            $productFilterSchema[] = Section::make("Filter opties voor $productFilter->name")->columnSpanFull()
                ->schema($productFiltersSchema)
                ->collapsible()
                ->persistCollapsed()
                ->columns(2)
                ->visible(fn(Get $get) => in_array($productFilter->id, $get('productFilters')));
        }
        //
        $newSchema[] = Section::make('Filters beheren')->columnSpanFull()
            ->headerActions([
                \Filament\Actions\Action::make('createMissingVariations')
                    ->label(fn($record) => "Ontbrekende variaties aanmaken (" . count($record->missing_variations ?? []) . ")")
                    ->visible(fn($livewire, $record, $get) => count($record->missing_variations ?? []) && $livewire instanceof EditProductGroup)
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        CreateMissingProductVariationsJob::dispatch($record);

                        Notification::make()
                            ->title('Missende variaties worden aangemaakt, refresh de pagina om de voortgang te zien')
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
                ->label('Kenmerk')
                ->options($productCharacteristics->pluck('name', 'id')->toArray())
                ->searchable()
                ->required(),
        ];
        foreach (Locales::getLocales() as $locale) {
            $productCharacteristicsTableColumns[] = TableColumn::make("Waarde ({$locale['id']})");
            $productCharacteristicsSchema[] = TextInput::make('value_' . $locale['id']);
        }

        $newSchema[] = Section::make('Kenmerken beheren')->columnSpanFull()
            ->schema([
                Repeater::make('productCharacteristics')
                    ->label('Kenmerken')
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
            ->hidden(fn($livewire, Get $get, $record) => $livewire instanceof CreateProductGroup || ($get('type') == 'variable' && (!$record && !$get('parent_id') || $record && !$record->parent_id)));

        $newSchema[] = Section::make('Content beheren')
            ->columnSpanFull()
            ->schema(array_merge([
                TextInput::make('name')
                    ->label('Naam')
                    ->maxLength(255)
                    ->required(),
                TextInput::make('slug')
                    ->label('Slug')
                    ->unique('dashed__product_groups', 'slug', fn($record) => $record)
                    ->helperText('Laat leeg om automatisch te laten genereren'),
                cms()->editorField('description', 'Uitgebreide beschrijving')
                    ->hintAction(
                        Action::make('generateDescription')
                            ->label('Genereer beschrijving')
                            ->icon(Heroicon::PencilSquare)
                            ->schema([
                                Textarea::make('description')
                                    ->label('Beschrijving')
                                    ->rows(7)
                                    ->required()
                                    ->helperText('Beschrijf hierin het product en bijvoorbeeld een voorbeeld beschrijving. De standaard prompt kan je aanpassen in vertalingen.'),
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
                            ->visible(fn($record) => $record && (bool)Customsetting::get('open_ai_api_key'))
                            ->action(function ($data, Set $set) {
                                $description = $data['description'] ?? '';
                                $description = OpenAIHelper::runPrompt(prompt: $description);
                                $set('description', $description);

                                Notification::make()
                                    ->title('De beschrijving is gegenereerd')
                                    ->success()
                                    ->send();
                            })
                    )
                    ->helperText('Mogelijke variablen: :name:, :categorie naam:')
                    ->rules([
                        'max:10000',
                    ])
                    ->columnSpan([
                        'default' => 1,
                        'lg' => 2,
                    ]),
                Textarea::make('short_description')
                    ->label('Korte beschrijving')
                    ->helperText('Mogelijke variablen: :name:, :categorie naam:')
                    ->rows(5)
                    ->maxLength(2500),
                Textarea::make('search_terms')
                    ->label('Zoekwoorden')
                    ->rows(2)
                    ->helperText('Vul hier termen in waar het product nog meer op gevonden moet kunnen worden. Deze termen gelden voor alle varianten.')
                    ->maxLength(2500),
                TextInput::make('order')
                    ->label('Volgorde')
                    ->required()
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(100000)
                    ->default(1),
                FileUpload::make('new_images')
                    ->label('Nieuwe afbeeldingen')
                    ->visible(fn($livewire) => $livewire instanceof EditProductGroup)
                    ->helperText('Deze afbeeldingen worden toegevoegd aan de product groep en achter de rest van de afbeeldingen geplaatst. Deze worden opgeslagen in de map: producten')
                    ->image()
                    ->preserveFilenames()
                    ->multiple()
                    ->columnSpanFull(),
                mediaHelper()->field('images', 'Afbeeldingen', required: false, multiple: true, defaultFolder: 'producten')
                    ->columnSpanFull()
                    ->helperText('Afbeeldingen van een variant worden VOOR de afbeelding van de product groep getoond'),
                cms()->getFilamentBuilderBlock(),
            ], static::customBlocksTab(['productBlocks', 'productGroupBlocks'])))
            ->collapsible()
            ->persistCollapsed()
            ->columns([
                'default' => 1,
                'lg' => 2,
            ]);

        $newSchema[] = Section::make('Linkjes beheren')
            ->columnSpanFull()
            ->schema([
                Select::make('productCategories')
                    ->multiple()
                    ->relationship('productCategories', 'name')
                    ->getSearchResultsUsing(fn($search) => RelationshipSearchQuery::make(ProductCategory::class, $search))
                    ->searchable()
                    ->getOptionLabelFromRecordUsing(fn($record) => $record->nameWithParents)
                    ->formatStateUsing(function ($state) {
                        return array_unique($state ?? []);
                    })
                    ->label('Link aan categorieeën')
                    ->helperText('Bovenliggende categorieën worden automatisch geactiveerd. Deze categorieen gelden voor alle varianten.'),
                Select::make('suggestedProducts')
                    ->multiple()
                    ->relationship('suggestedProducts', 'name')
                    ->getSearchResultsUsing(fn($search) => RelationshipSearchQuery::make(Product::class, $search))
                    ->getOptionLabelFromRecordUsing(fn($record) => $record->nameWithParents)
                    ->helperText('Indien je bij een variant ook voorgestelde producten koppelt, worden deze samengevoegd')
                    ->label('Link voorgestelde producten'),
                Select::make('crossSellProducts')
                    ->multiple()
                    ->relationship('crossSellProducts', 'name')
                    ->getSearchResultsUsing(fn($search) => RelationshipSearchQuery::make(Product::class, $search))
                    ->getOptionLabelFromRecordUsing(fn($record) => $record->nameWithParents)
                    ->label('Link cross sell producten')
                    ->helperText('Dit mogen alleen maar producten zijn die zonder verplichte opties zijn. Indien je bij een variant ook cross sell producten koppelt, worden deze samengevoegd'),
                Select::make('globalProductExtras')
                    ->multiple()
                    ->preload()
                    ->relationship('globalProductExtras', 'name')
                    ->getOptionLabelFromRecordUsing(fn($record) => $record->name)
                    ->getSearchResultsUsing(fn($search, $query) => RelationshipSearchQuery::make(ProductExtra::class, $search, applyScopes: 'isGlobal'))
                    ->helperText('Indien je bij een variant ook product extras koppelt, worden deze samengevoegd')
                    ->label('Link globale product extras'),
                Select::make('globalProductTabs')
                    ->multiple()
                    ->getSearchResultsUsing(fn($search, $query) => RelationshipSearchQuery::make(ProductTab::class, $search, applyScopes: 'isGlobal'))
                    ->preload()
                    ->relationship('globalTabs', 'name')
                    ->getOptionLabelFromRecordUsing(fn($record) => $record->name)
                    ->helperText('Indien je bij een variant ook product tabs koppelt, worden deze samengevoegd')
                    ->label('Link globale product tabs'),
            ])
            ->columns([
                'default' => 1,
                'lg' => 2,
            ])
            ->persistCollapsed()
            ->collapsible();

        $newSchema[] = Section::make('Product extras')->columnSpanFull()
            ->schema([
                Repeater::make('productExtras')
                    ->relationship('productExtras')
                    ->columns(2)
                    ->cloneable()
                    ->schema(array_merge(ProductExtra::getFilamentFields(), static::customBlocksTab('productExtraOptionBlocks'))),
            ])
            ->hidden(fn($livewire) => $livewire instanceof CreateProductGroup)
            ->collapsible()
            ->persistCollapsed();

        $newSchema[] = Section::make('Product tabs')->columnSpanFull()
            ->schema([
                Repeater::make('tabs')
                    ->label('Tabs')
                    ->relationship('ownTabs')
                    ->cloneable()
                    ->schema([
                        TextInput::make('name')
                            ->label('Naam')
                            ->required()
                            ->maxLength(100),
                        cms()->editorField('content', 'Content')
                            ->required(),
                    ]),
            ])
            ->hidden(fn($livewire) => $livewire instanceof CreateProductGroup)
            ->collapsible()
            ->persistCollapsed();

        $newSchema[] = Section::make('Volume korting')
            ->columnSpanFull()
            ->schema([
                Repeater::make('volumeDiscounts')
                    ->relationship('volumeDiscounts')
                    ->label('Volume korting')
                    ->cloneable()
                    ->reorderable()
                    ->columnSpanFull()
                    ->schema([
                        Select::make('type')
                            ->label('Type')
                            ->options([
                                'percentage' => 'Percentage',
                                'fixed' => 'Vast bedrag',
                            ])
                            ->default('percentage')
                            ->required()
                            ->reactive(),
                        TextInput::make('min_quantity')
                            ->label('Vanaf aantal')
                            ->required()
                            ->default(5),
                        TextInput::make('discount_price')
                            ->label('Kortings prijs')
                            ->numeric()
                            ->required()
                            ->visible(fn(Get $get) => $get('type') == 'fixed')
                            ->prefix('€'),
                        TextInput::make('discount_percentage')
                            ->label('Kortings percentage')
                            ->numeric()
                            ->required()
                            ->visible(fn(Get $get) => $get('type') == 'percentage')
                            ->suffix('%'),
                        Toggle::make('active_for_all_variants')
                            ->label('Actief voor alle varianten')
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
                            ->getOptionLabelFromRecordUsing(fn($record) => $record->nameWithParents)
                            ->label('Korting alleen voor deze producten')
                            ->visible(fn(Get $get) => !$get('active_for_all_variants'))
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
            ->hidden(fn($livewire) => $livewire instanceof CreateProductGroup)
            ->persistCollapsed()
            ->collapsible();

        $newSchema[] = Section::make('Meta data')->columnSpanFull()
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
                    ->getStateUsing(fn($record) => $record->images ? (mediaHelper()->getSingleMedia($record->images[0], 'original')->url ?? '') : null)
                    ->label(''),
                TextColumn::make('name')
                    ->label('Naam')
                    ->searchable(query: SearchQuery::make())
                    ->sortable(),
                TextColumn::make('products_sum_purchases')
//                    ->sum('products', 'purchases')
                    ->label('Aantal verkopen')
                    ->getStateUsing(fn(ProductGroup $record) => $record->products->sum('total_purchases')),
                TextColumn::make('products_count')
                    ->counts('products')
                    ->label('Aantal producten')
                    ->sortable(),
            ], static::visitableTableColumns()))
//            ->reorderable('order')
            ->recordActions([
                EditAction::make()
                    ->button(),
                DeleteAction::make(),
            ])
            ->toolbarActions(ToolbarActions::getActions())
            ->filters([
                Filter::make('categories')
                    ->schema([
                        Select::make('categories')
                            ->multiple()
                            ->label('Categorieen')
                            ->options(ProductCategory::all()->pluck('name', 'id')),
                    ])
                    ->query(function (\Illuminate\Database\Eloquent\Builder $query, array $data): \Illuminate\Database\Eloquent\Builder {
                        if (!$data['categories']) {
                            return $query;
                        }

                        return $query->whereHas('productCategories', fn(\Illuminate\Database\Eloquent\Builder $query) => $query->whereIn('product_category_id', $data['categories']));
                    }),
            ]);
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
}
