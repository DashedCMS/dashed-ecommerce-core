<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources;

use Closure;
use Filament\Forms\Get;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Filters\Filter;
use Dashed\DashedCore\Classes\Sites;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Dashed\DashedCore\Classes\Locales;
use Filament\Forms\Components\Section;
use FilamentTiptapEditor\TiptapEditor;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\BulkActionGroup;
use Illuminate\Database\Eloquent\Collection;
use Filament\Resources\Concerns\Translatable;
use Filament\Tables\Actions\DeleteBulkAction;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Models\ProductFilter;
use Dashed\DashedEcommerceCore\Models\ProductCategory;
use Dashed\DashedCore\Classes\QueryHelpers\SearchQuery;
use Dashed\DashedCore\Filament\Concerns\HasVisitableTab;
use Dashed\DashedCore\Filament\Concerns\HasCustomBlocksTab;
use Dashed\DashedEcommerceCore\Models\ProductCharacteristics;
use Dashed\DashedEcommerceCore\Jobs\CreateMissingProductVariationsJob;
use Dashed\DashedEcommerceCore\Filament\Resources\ProductResource\Pages\EditProduct;
use Dashed\DashedEcommerceCore\Filament\Resources\ProductResource\Pages\ListProducts;
use Dashed\DashedEcommerceCore\Filament\Resources\ProductResource\Pages\CreateProduct;
use Dashed\DashedEcommerceCore\Filament\Resources\ProductResource\RelationManagers\ChildProductsRelationManager;

class ProductResource extends Resource
{
    use Translatable;
    use HasVisitableTab;
    use HasCustomBlocksTab;

    protected static ?string $model = Product::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';
    protected static ?string $navigationGroup = 'E-commerce';
    protected static ?string $navigationLabel = 'Producten';
    protected static ?string $label = 'Product';
    protected static ?string $pluralLabel = 'Producten';
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
            'sku',
            'ean',
            'content',
        ];
    }

    public static function form(Form $form): Form
    {
        config(['filament-tiptap-editor.directory' => 'dashed/products/images']);

        $schema = [];

        $schema[] = Section::make('Algemene instellingen')
            ->schema([
                Select::make('type')
                    ->label('Soort product')
                    ->options([
                        'simple' => 'Simpel',
                        'variable' => 'Variabel',
                    ])
                    ->default('simple')
                    ->required()
                    ->reactive()
                    ->hidden(fn ($record) => $record && $record->parent_id),
                Select::make('parent_id')
                    ->label('Bovenliggende product')
                    ->options(Product::where('type', 'variable')->where('id', '!=', $record->id ?? 0)->whereNull('parent_id')->pluck('name', 'id'))
                    ->reactive()
                    ->searchable()
                    ->helperText('Als je het bovenliggende product aanpast, moet je alle filters etc controleren.')
                    ->visible(fn (Get $get, $livewire, $record) => $get('type') == 'variable' && ($livewire instanceof CreateProduct || ($livewire instanceof EditProduct && $record->parent_id))),
                Select::make('site_ids')
                    ->multiple()
                    ->label('Actief op sites')
                    ->options(collect(Sites::getSites())->pluck('name', 'id')->toArray())
                    ->default([Sites::getFirstSite()['id']])
                    ->hidden(fn (Get $get) => ! (Sites::getAmountOfSites() > 1) || $get('parent_id') && $get('type') == 'variable')
                    ->required()
                    ->disabled(fn ($record) => $record && $record->parent_id),
                Toggle::make('public')
                    ->label('Openbaar')
                    ->default(1),
                Toggle::make('is_bundle')
                    ->label('Bundel product')
                    ->helperText('Bestaat dit product uit meerdere andere producten?')
                    ->hidden(fn ($record, Get $get) => $get('type') == 'variable' && ! $get('parent_id'))
                    ->reactive(),
                Toggle::make('use_bundle_product_price')
                    ->label('Gebruik onderliggend bundel product prijs')
                    ->hidden(fn ($record, Get $get) => $get('type') == 'variable' && ! $get('parent_id'))
                    ->visible(fn ($get) => $get('is_bundle'))
                    ->reactive(),
                Repeater::make('bundleProducts')
                    ->relationship('bundleProducts')
                    ->saveRelationshipsWhenHidden(false)
                    ->saveRelationshipsUsing(function ($record, $state) {
                        $bundleProductIds = [];

                        foreach ($state as $bundleProduct) {
                            if ($bundleProduct['bundle_product_id']) {
                                $bundleProductIds[] = $bundleProduct['bundle_product_id'];
                            }
                        }

                        $record->bundleProducts()->sync($bundleProductIds);
                    })
                    ->name('Bundel producten')
                    ->reactive()
                    ->schema([
                        Select::make('bundle_product_id')
                            ->label('Bundel product')
                            ->searchable()
                            ->getSearchResultsUsing(fn (string $query) => Product::notParentProduct()->isNotBundle()->where('name', 'like', "%{$query}%")->limit(50)->pluck('name', 'id'))
                            ->getOptionLabelUsing(fn ($value): ?string => Product::find($value)?->name)
                            ->required(),
                    ])
                    ->required()
                    ->rules([
                        'required',
                        function () {
                            return function (string $attribute, $value, Closure $fail) {
                                $bundleProductIds = [];
                                foreach ($value as $bundleProduct) {
                                    if (! in_array($bundleProduct['bundle_product_id'], $bundleProductIds)) {
                                        $bundleProductIds[] = $bundleProduct['bundle_product_id'];
                                    } else {
                                        $fail("You cannot add more then 1 of the same product in the bundle products.");
                                    }
                                }
                            };
                        },
                    ])
                    ->hidden(fn ($record, Get $get) => $get('type') == 'variable' && ! $get('parent_id'))
                    ->visible(fn (Get $get) => $get('is_bundle')),
                Toggle::make('only_show_parent_product')
                    ->label('Toon 1 variatie op overzichtspagina')
                    ->hidden(fn ($record, Get $get) => $get('type') != 'variable' || ($record && $record->parent_id)),
                Select::make('copyable_to_childs')
                    ->label('Welke onderdelen moeten gekopieerd worden naar de variaties?')
                    ->multiple()
                    ->searchable()
                    ->preload()
                    ->helperText('Let op: dit OVERSCHRIJFT de huidige waardes van de variaties')
                    ->options([
                        'images' => 'Afbeeldingen',
                        'productCategories' => 'Product categorieën',
                        'shippingClasses' => 'Verzendklasses',
                        'suggestedProducts' => 'Voorgestelde producten',
                        'content' => 'Content',
                        'description' => 'Uitgebreide beschrijving',
                        'short_description' => 'Korte beschrijving',
                    ])
                    ->hidden(fn ($record, Get $get) => $get('type') != 'variable' || ($record && $record->parent_id)),
            ])
            ->collapsed(fn ($livewire) => $livewire instanceof EditProduct);

        $schema[] = Section::make('Voorraad beheren')
            ->schema([
                Toggle::make('use_stock')
                    ->label('Voorraad bijhouden')
                    ->reactive(),
                TextInput::make('stock')
                    ->type('number')
                    ->label('Hoeveel heb je van dit product op voorraad')
                    ->helperText(fn ($record) => $record ? 'Er zijn er momenteel ' . $record->reservedStock() . ' gereserveerd' : '')
                    ->maxValue(100000)
                    ->required()
                    ->numeric()
                    ->hidden(fn (Get $get) => ! $get('use_stock')),
                Toggle::make('out_of_stock_sellable')
                    ->label('Product doorverkopen wanneer niet meer op voorraad (pre-orders)')
                    ->reactive()
                    ->hidden(fn (Get $get) => ! $get('use_stock')),
                DatePicker::make('expected_in_stock_date')
                    ->label('Wanneer komt dit product weer op voorraad')
                    ->reactive()
                    ->required()
                    ->hidden(fn (Get $get) => ! $get('use_stock') || ! $get('out_of_stock_sellable')),
                Toggle::make('low_stock_notification')
                    ->label('Ik wil een melding krijgen als dit product laag op voorraad raakt')
                    ->reactive()
                    ->hidden(fn (Get $get) => ! $get('use_stock')),
                TextInput::make('low_stock_notification_limit')
                    ->label('Als de voorraad van dit product onder onderstaand nummer komt, krijg je een notificatie')
                    ->type('number')
                    ->reactive()
                    ->required()
                    ->minValue(1)
                    ->maxValue(100000)
                    ->default(1)
                    ->numeric()
                    ->hidden(fn (Get $get) => ! $get('use_stock') || ! $get('low_stock_notification')),
                Select::make('stock_status')
                    ->label('Is dit product op voorraad')
                    ->options([
                        'in_stock' => 'Op voorraad',
                        'out_of_stock' => 'Uitverkocht',
                    ])
                    ->default('in_stock')
                    ->required()
                    ->hidden(fn (Get $get) => $get('use_stock')),
                Toggle::make('limit_purchases_per_customer')
                    ->label('Dit product mag maar een x aantal keer per bestelling gekocht worden')
                    ->reactive(),
                TextInput::make('limit_purchases_per_customer_limit')
                    ->type('number')
                    ->label('Hoeveel mag dit product gekocht worden per bestelling')
                    ->minValue(1)
                    ->maxValue(100000)
                    ->default(1)
                    ->required()
                    ->numeric()
                    ->hidden(fn (Get $get) => ! $get('limit_purchases_per_customer')),
            ])
            ->hidden(fn ($record, Get $get) => $get('type') == 'variable' && (! $record && ! $get('parent_id') || $record && ! $record->parent_id))
            ->collapsible();

        $productFilters = ProductFilter::with(['productFilterOptions'])->get();
        $productFilterSchema = [];

        foreach ($productFilters as $productFilter) {
            $productFiltersSchema = [];
            $productFilterSchema[] = Toggle::make("product_filter_$productFilter->id")
                ->label("Filter $productFilter->name")
                ->reactive()
                ->columnSpan([
                    'default' => 1,
                    'lg' => 2,
                ])
                ->hidden(fn ($record, Get $get) => $get('type') == 'variable' && $record && $record->parent_id);
            $productFilterSchema[] = Toggle::make("product_filter_{$productFilter->id}_use_for_variations")
                ->label("$productFilter->name gebruiken voor variaties op de product pagina")
                ->visible(fn (Get $get) => $get("product_filter_$productFilter->id"))
                ->reactive()
                ->columnSpan([
                    'default' => 1,
                    'lg' => 2,
                ])
                ->hidden(fn ($record, Get $get) => $get('type') == 'variable' && $record && $record->parent_id);

            $productEnabledFiltersSchema = [];
            foreach ($productFilter->productFilterOptions as $productFilterOption) {
                $productEnabledFiltersSchema[] = Checkbox::make("product_filter_{$productFilter->id}_option_{$productFilterOption->id}_enabled")
                    ->label("$productFilter->name: $productFilterOption->name aanzetten voor variaties");
            }
            $productFilterSchema[] = Section::make("Geactiveerde filter opties voor $productFilter->name")
                ->schema($productEnabledFiltersSchema)
                ->collapsible()
                ->collapsed()
                ->visible(fn (Get $get, $record) => $get("product_filter_$productFilter->id") && $get('type') == 'variable' && $record && ! $record->parent_id);

            foreach ($productFilter->productFilterOptions as $productFilterOption) {
                $productFiltersSchema[] = Checkbox::make("product_filter_{$productFilter->id}_option_{$productFilterOption->id}")
                    ->label("$productFilter->name: $productFilterOption->name")
                    ->visible(fn ($record) => ($record->parent && $record->parent->enabledProductFilterOptions()->wherePivot('product_filter_option_id', $productFilterOption->id)->count()) || $record->type == 'simple');
            }

            $productFilterSchema[] = Section::make("Filter opties voor $productFilter->name")
                ->schema($productFiltersSchema)
                ->collapsible()
                ->collapsed()
                ->hidden(fn (Get $get, $record) => ! $get("product_filter_$productFilter->id") || ($get('type') == 'variable' && $record && ! $record->parent_id));
        }

        $schema[] = Section::make('Filters beheren')
            ->headerActions([
                \Filament\Forms\Components\Actions\Action::make('createMissingVariations')
                    ->label(fn ($record) => "Ontbrekende variaties aanmaken (" . $record->missing_variations . ")")
                    ->visible(fn ($livewire, $record, $get) => $record->missing_variations && $livewire instanceof EditProduct && $get('type') == 'variable' && $record && ! $record->parent_id)
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
            ->columns([
                'default' => 1,
                'lg' => 2,
            ])
            ->collapsed(fn ($livewire) => $livewire instanceof EditProduct)
            ->hidden(fn ($livewire) => $livewire instanceof CreateProduct);

        $productCharacteristics = ProductCharacteristics::orderBy('order', 'ASC')->get();
        $productCharacteristicSchema = [];

        foreach (Locales::getLocales() as $locale) {
            foreach ($productCharacteristics as $productCharacteristic) {
                $productCharacteristicSchema[] = TextInput::make("product_characteristic_{$productCharacteristic->id}_{$locale['id']}")
                    ->label($productCharacteristic->getTranslation('name', $locale['id']) . ' (' . $locale['id'] . ')');
            }
        }

        //Not possible in another way because it is filled in pivot table
        $schema[] = Section::make('Kenmerken beheren')
            ->schema($productCharacteristicSchema)
            ->columns([
                'default' => 1,
                'lg' => 3,
            ])
            ->collapsed(fn ($livewire) => $livewire instanceof EditProduct)
            ->hidden(fn ($livewire, Get $get, $record) => $livewire instanceof CreateProduct || ($get('type') == 'variable' && (! $record && ! $get('parent_id') || $record && ! $record->parent_id)));

        $schema[] = Section::make('Content beheren')
            ->schema(array_merge([
                TextInput::make('name')
                    ->label('Naam')
                    ->maxLength(255)
                    ->required(),
                TextInput::make('slug')
                    ->label('Slug')
                    ->unique('dashed__products', 'slug', fn ($record) => $record)
                    ->helperText('Laat leeg om automatisch te laten genereren'),
                TiptapEditor::make('description')
                    ->label('Uitgebreide beschrijving')
                    ->rules([
                        'max:10000',
                    ])
                    ->columnSpan([
                        'default' => 1,
                        'lg' => 2,
                    ]),
//                    ->hidden(fn($record, Get $get) => $get('type') == 'variable' && (!$record && !$get('parent_id') || $record && !$record->parent_id)),
                Textarea::make('short_description')
                    ->label('Korte beschrijving')
                    ->rows(5)
                    ->maxLength(2500),
//                    ->hidden(fn($record, Get $get) => $get('type') == 'variable' && (!$record && !$get('parent_id') || $record && !$record->parent_id)),
                Textarea::make('search_terms')
                    ->label('Zoekwoorden')
                    ->rows(2)
                    ->helperText('Vul hier termen in waar het product nog meer op gevonden moet kunnen worden')
                    ->maxLength(2500),
//                    ->hidden(fn($record, Get $get) => $get('type') == 'variable' && (!$record && !$get('parent_id') || $record && !$record->parent_id)),
                TextInput::make('order')
                    ->label('Volgorde')
                    ->required()
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(100000)
                    ->default(1),
                cms()->getFilamentBuilderBlock(),
//                    ->hidden(fn($record, Get $get) => $get('type') == 'variable' && (!$record && !$get('parent_id') || $record && !$record->parent_id)),
            ], static::customBlocksTab(cms()->builder('productBlocks'))))
            ->columns([
                'default' => 1,
                'lg' => 2,
            ]);

        $schema[] = Section::make('Meta')
            ->schema(static::metadataTab())
            ->hidden(fn ($record, Get $get) => $get('type') == 'variable' && (! $record && ! $get('parent_id') || $record && ! $record->parent_id));

        $schema[] = Section::make('Afbeeldingen beheren')
            ->schema([
                Repeater::make('images')
                    ->label('Afbeeldingen')
                    ->schema([
                        mediaHelper()->field('image', 'Afbeelding', true, false, true),
                        TextInput::make('alt_text')
                            ->label('Alt tekst')
                            ->maxLength(1000),
                    ])
                    ->defaultItems(0)
                    ->addActionLabel('Nieuwe afbeelding toevoegen'),
            ])
            ->collapsible();

        $schema[] = Section::make('Praktische informatie beheren')
            ->schema([
                TextInput::make('price')
                    ->label('Prijs van het product')
                    ->helperText('Voorbeeld: 10.25')
                    ->prefix('€')
                    ->minValue(0)
                    ->maxValue(100000)
                    ->required()
                    ->numeric(),
                TextInput::make('new_price')
                    ->label('Vorige prijs (de hogere prijs)')
                    ->helperText('Voorbeeld: 14.25')
                    ->prefix('€')
                    ->minValue(0)
                    ->maxValue(100000)
                    ->numeric(),
                TextInput::make('vat_rate')
                    ->label('BTW percentage')
                    ->helperText('21%, 9%, 0% of anders')
                    ->required()
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(100)
                    ->default(21),
                TextInput::make('sku')
                    ->label('SKU van het product')
                    ->helperText('Vaak gebruikt voor interne herkenning')
                    ->maxLength(255)
                    ->required()
                    ->default('SKU' . rand(10000, 99999)),
                TextInput::make('ean')
                    ->label('EAN van het product')
                    ->helperText('Dit is een code die gekoppeld zit aan dit specifieke product')
                    ->maxLength(255),
                TextInput::make('weight')
                    ->label('Gewicht')
                    ->helperText('Berekend in KG')
                    ->maxLength(100000)
                    ->numeric(),
                TextInput::make('length')
                    ->label('Lengte')
                    ->helperText('Berekend in CM')
                    ->maxLength(100000)
                    ->numeric(),
                TextInput::make('width')
                    ->label('Breedte')
                    ->helperText('Berekend in CM')
                    ->maxLength(100000)
                    ->numeric(),
                TextInput::make('height')
                    ->label('Hoogte')
                    ->helperText('Berekend in CM')
                    ->maxLength(100000)
                    ->numeric(),
            ])
            ->columns(['default' => 1,
                'lg' => 2,])
            ->hidden(fn ($record, Get $get) => $get('type') == 'variable' && (! $record && ! $get('parent_id') || $record && ! $record->parent_id))
            ->collapsible();

        $schema[] = Section::make('Linkjes beheren')
            ->schema([
                Select::make('productCategories')
                    ->multiple()
                    ->preload()
                    ->relationship('productCategories', 'name')
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->name)
                    ->label('Link aan categorieeën')
                    ->helperText('Bovenliggende categorieën worden automatisch geactiveerd'),
                Select::make('shippingClasses')
                    ->multiple()
                    ->preload()
                    ->relationship('shippingClasses', 'name')
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->name)
                    ->label('Link verzendklasses'),
                Select::make('suggestedProducts')
                    ->multiple()
                    ->preload()
                    ->relationship('suggestedProducts', 'name')
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->name)
                    ->label('Link voorgestelde producten'),])
            ->columns([
                'default' => 1,
                'lg' => 2,
            ])
//            ->hidden(fn ($record, Get $get) => $get('type') == 'variable' && (! $record && ! $get('parent_id') || $record && ! $record->parent_id))
            ->collapsible();

        $schema[] = Section::make('Product extras')
            ->schema([
                Repeater::make('productExtras')
                    ->relationship('productExtras')
                    ->columns(2)
                    ->cloneable()
                    ->schema(array_merge([
                        TextInput::make('name')
                            ->label('Naam')
                            ->required()
                            ->maxLength(255),
                        Select::make('type')
                            ->label('Type')
                            ->options([
                                'single' => '1 optie',
                                'multiple' => 'Meerdere opties',
                                'checkbox' => 'Checkbox',
                                'input' => 'Invulveld',
                                'imagePicker' => 'Afbeelding kiezen',
                                'file' => 'Upload bestand',
                            ])
                            ->default('single')
                            ->required()
                            ->reactive(),
                        Select::make('input_type')
                            ->label('Input type')
                            ->options([
                                'text' => 'Tekst',
                                'numeric' => 'Getal',
                                'date' => 'Datum',
                                'dateTime' => 'Datum + tijd',
                            ])
                            ->default('text')
                            ->visible(fn (Get $get) => $get('type') == 'input')
                            ->required(fn (Get $get) => $get('type') == 'input'),
                        TextInput::make('min_length')
                            ->label('Minimale lengte/waarde')
                            ->numeric()
                            ->visible(fn (Get $get) => $get('type') == 'input')
                            ->required(fn (Get $get) => $get('type') == 'input'),
                        TextInput::make('max_length')
                            ->label('Maximale lengte/waarde')
                            ->numeric()
                            ->visible(fn (Get $get) => $get('type') == 'input')
                            ->required(fn (Get $get) => $get('type') == 'input')
                            ->reactive(),
                        Toggle::make('required')
                            ->label('Verplicht'),
                        Repeater::make('productExtraOptions')
                            ->relationship('productExtraOptions')
                            ->cloneable(fn (Get $get) => $get('type') != 'checkbox')
                            ->label('Opties van deze product extra')
                            ->visible(fn (Get $get) => $get('type') == 'single' || $get('type') == 'multiple' || $get('type') == 'checkbox' || $get('type') == 'imagePicker')
                            ->required(fn (Get $get) => $get('type') == 'single' || $get('type') == 'multiple' || $get('type') == 'checkbox' || $get('type') == 'imagePicker')
                            ->maxItems(fn (Get $get) => $get('type') == 'checkbox' ? 1 : 50)
                            ->reactive()
                            ->schema([
                                TextInput::make('value')
                                    ->label('Waarde')
                                    ->required()
                                    ->maxLength(255),
                                TextInput::make('price')
                                    ->required()
                                    ->label('Meerprijs van deze optie')
                                    ->prefix('€')
                                    ->helperText('Voorbeeld: 10.25')
                                    ->numeric()
                                    ->minValue(0.00)
                                    ->maxValue(10000),
                                mediaHelper()->field('image', 'Afbeelding'),
                                Toggle::make('calculate_only_1_quantity')
                                    ->label('Deze extra maar 1x meetellen, ook al worden er meerdere van het product gekocht'),
                            ])
                            ->columnSpan(2),
                    ], static::customBlocksTab(cms()->builder('productExtraOptionBlocks')))),
            ])
            ->hidden(fn ($livewire) => $livewire instanceof CreateProduct)
            ->collapsible()
            ->collapsed();

        return $form->schema($schema);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns(array_merge([
                TextColumn::make('name')
                    ->label('Naam')
                    ->searchable(query: SearchQuery::make())
                    ->sortable(),
                TextColumn::make('total_purchases')
                    ->label('Aantal verkopen')
                    ->sortable(),
            ], static::visitableTableColumns()))
            ->reorderable('order')
            ->actions([
                EditAction::make()
                    ->button(),
                Action::make('quickActions')
                    ->button()
                    ->label('Quick')
                    ->color('primary')
                    ->modalHeading('Snel bewerken')
                    ->modalSubmitActionLabel('Opslaan')
                    ->form([
                        Section::make('Beheer de prijzen')
                            ->schema([
                                TextInput::make('price')
                                    ->label('Prijs van het product')
                                    ->helperText('Voorbeeld: 10.25')
                                    ->prefix('€')
                                    ->minValue(0)
                                    ->maxValue(100000)
                                    ->required()
                                    ->default(fn ($record) => $record->price),
                                TextInput::make('new_price')
                                    ->label('Vorige prijs (de hogere prijs)')
                                    ->helperText('Voorbeeld: 14.25')
                                    ->prefix('€')
                                    ->minValue(0)
                                    ->maxValue(100000)
                                    ->default(fn ($record) => $record->new_price),
                            ])
                            ->columns([
                                'default' => 1,
                                'lg' => 2,
                            ]),
                        Section::make('Voorraad beheren')
                            ->schema([
                                Toggle::make('use_stock')
                                    ->default(fn ($record) => $record->use_stock)
                                    ->label('Voorraad bijhouden')
                                    ->reactive(),
                                TextInput::make('stock')
                                    ->default(fn ($record) => $record->stock)
                                    ->type('number')
                                    ->label('Hoeveel heb je van dit product op voorraad')
                                    ->maxValue(100000)
                                    ->required()
                                    ->hidden(fn (Get $get) => ! $get('use_stock')),
                                Toggle::make('out_of_stock_sellable')
                                    ->default(fn ($record) => $record->out_of_stock_sellable)
                                    ->label('Product doorverkopen wanneer niet meer op voorraad (pre-orders)')
                                    ->reactive()
                                    ->hidden(fn (Get $get) => ! $get('use_stock')),
                                DatePicker::make('expected_in_stock_date')
                                    ->default(fn ($record) => $record->expected_in_stock_date)
                                    ->label('Wanneer komt dit product weer op voorraad')
                                    ->reactive()
                                    ->required()
                                    ->hidden(fn (Get $get) => ! $get('use_stock') || ! $get('out_of_stock_sellable')),
                                Toggle::make('low_stock_notification')
                                    ->default(fn ($record) => $record->low_stock_notification)
                                    ->label('Ik wil een melding krijgen als dit product laag op voorraad raakt')
                                    ->reactive()
                                    ->hidden(fn (Get $get) => ! $get('use_stock')),
                                TextInput::make('low_stock_notification_limit')
                                    ->default(fn ($record) => $record->low_stock_notification_limit)
                                    ->label('Als de voorraad van dit product onder onderstaand nummer komt, krijg je een notificatie')
                                    ->type('number')
                                    ->reactive()
                                    ->required()
                                    ->minValue(1)
                                    ->maxValue(100000)
                                    ->default(1)
                                    ->required()
                                    ->hidden(fn (Get $get) => ! $get('use_stock') || ! $get('low_stock_notification')),
                                Select::make('stock_status')
                                    ->default(fn ($record) => $record->stock_status ?: 'in_stock')
                                    ->label('Is dit product op voorraad')
                                    ->options([
                                        'in_stock' => 'Op voorraad',
                                        'out_of_stock' => 'Uitverkocht',
                                    ])
                                    ->required()
                                    ->hidden(fn (Get $get) => $get('use_stock')),
                                Toggle::make('limit_purchases_per_customer')
                                    ->default(fn ($record) => $record->limit_purchases_per_customer)
                                    ->label('Dit product mag maar een x aantal keer per bestelling gekocht worden')
                                    ->reactive(),
                                TextInput::make('limit_purchases_per_customer_limit')
                                    ->default(fn ($record) => $record->limit_purchases_per_customer_limit)
                                    ->type('number')
                                    ->label('Hoeveel mag dit product gekocht worden per bestelling')
                                    ->minValue(1)
                                    ->maxValue(100000)
                                    ->default(1)
                                    ->required()
                                    ->hidden(fn (Get $get) => ! $get('limit_purchases_per_customer')),
                            ]),
                    ])
                    ->action(function (Product $record, array $data): void {
                        foreach ($data as $key => $value) {
                            $record[$key] = $value;
                        }
                        $record->save();

                        Notification::make()
                            ->title('Het product is aangepast')
                            ->success()
                            ->send();
                    })
                    ->hidden(fn ($record) => $record->type == 'variable' && ! $record->parent_id),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    BulkAction::make('changePrice')
                        ->color('primary')
                        ->label('Verander prijzen')
                        ->form([
                            TextInput::make('price')
                                ->label('Prijs van het product')
                                ->helperText('Voorbeeld: 10.25')
                                ->prefix('€')
                                ->numeric()
                                ->minValue(0)
                                ->maxValue(100000)
                                ->required(),
                            TextInput::make('new_price')
                                ->label('Vorige prijs (de hogere prijs)')
                                ->helperText('Voorbeeld: 14.25')
                                ->prefix('€')
                                ->numeric()
                                ->minValue(0)
                                ->maxValue(100000),
                        ])
                        ->action(function (Collection $records, array $data): void {
                            foreach ($records as $record) {
                                $record->price = $data['price'];
                                $record->new_price = $data['new_price'];
                                $record->save();
                            }

                            Notification::make()
                                ->title('Het product is aangepast')
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('changePublicStatus')
                        ->color('primary')
                        ->label('Verander publieke status')
                        ->form([
                            Toggle::make('public')
                                ->label('Openbaar')
                                ->default(1),
                        ])
                        ->action(function (Collection $records, array $data): void {
                            foreach ($records as $record) {
                                $record->public = $data['public'];
                                $record->save();
                            }

                            Notification::make()
                                ->title('Het product is aangepast')
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ])
            ->filters([
                Filter::make('onlyParentProducts')
                    ->form([
                        Toggle::make('value')
                            ->label('Alleen hoofd producten')
                            ->default(1),
                    ])
                    ->query(function (\Illuminate\Database\Eloquent\Builder $query, array $data): \Illuminate\Database\Eloquent\Builder {
                        return $query
                            ->when(
                                $data['value'],
                                fn (\Illuminate\Database\Eloquent\Builder $query, $value): \Illuminate\Database\Eloquent\Builder => $query->topLevel(),
                            );
                    }),
                Filter::make('categories')
                    ->form([
                        Select::make('categories')
                            ->multiple()
                            ->label('Categorieen')
                            ->options(ProductCategory::all()->pluck('name', 'id')),
                    ])
                    ->query(function (\Illuminate\Database\Eloquent\Builder $query, array $data): \Illuminate\Database\Eloquent\Builder {
                        if (! $data['categories']) {
                            return $query;
                        }

                        return $query->whereHas('productCategories', fn (\Illuminate\Database\Eloquent\Builder $query) => $query->whereIn('product_category_id', $data['categories']));
                    }),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            ChildProductsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProducts::route('/'),
            'create' => CreateProduct::route('/create'),
            'edit' => EditProduct::route('/{record}/edit'),
        ];
    }
}
