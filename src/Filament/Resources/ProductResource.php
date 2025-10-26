<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources;

use Closure;
use UnitEnum;
use BackedEnum;
use Filament\Tables\Table;
use Filament\Actions\Action;
use Filament\Schemas\Schema;
use Filament\Actions\BulkAction;
use Filament\Actions\EditAction;
use Filament\Resources\Resource;
use Filament\Actions\DeleteAction;
use Filament\Tables\Filters\Filter;
use Dashed\DashedCore\Classes\Sites;
use Filament\Actions\BulkActionGroup;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Dashed\DashedCore\Classes\Locales;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Tables\Columns\ImageColumn;
use Filament\Forms\Components\FileUpload;
use Illuminate\Database\Eloquent\Collection;
use Dashed\DashedEcommerceCore\Models\Product;
use Filament\Schemas\Components\Utilities\Get;
use Dashed\DashedEcommerceCore\Models\ProductExtra;
use Dashed\DashedEcommerceCore\Models\ProductGroup;
use Dashed\DashedEcommerceCore\Models\ProductCategory;
use Dashed\DashedCore\Classes\QueryHelpers\SearchQuery;
use Dashed\DashedCore\Filament\Concerns\HasVisitableTab;
use Dashed\DashedCore\Filament\Concerns\HasCustomBlocksTab;
use Dashed\DashedEcommerceCore\Models\ProductCharacteristics;
use LaraZeus\SpatieTranslatable\Resources\Concerns\Translatable;
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

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-shopping-bag';
    protected static string|UnitEnum|null $navigationGroup = 'Producten';
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
            'product_search_terms',
            'sku',
            'ean',
            'content',
        ];
    }

    public static function form(Schema $schema): Schema
    {
        config(['filament-tiptap-editor.directory' => 'dashed/products/images']);

        $newSchema = [];

        $newSchema[] = Section::make('Algemene instellingen')
            ->columnSpanFull()
            ->schema([
                Select::make('site_ids')
                    ->multiple()
                    ->label('Actief op sites')
                    ->options(collect(Sites::getSites())->pluck('name', 'id')->toArray())
                    ->default([Sites::getFirstSite()['id']])
                    ->hidden(fn (Get $get) => ! (Sites::getAmountOfSites() > 1))
                    ->required(),
                Select::make('product_group_id')
                    ->label('Product groep')
                    ->options(ProductGroup::all()->pluck('name', 'id')->toArray())
                    ->searchable()
                    ->helperText('Dit waren voorheen bovenliggende producten, voortaan moet ELK product onder een product groep hangen')
                    ->default(request()->get('productGroupId'))
//                    ->disabled(fn ($livewire) => ! ($livewire instanceof CreateProduct) || request()->get('productGroupId'))
                    ->columnSpanFull()
                    ->required(),
                Toggle::make('public')
                    ->label('Openbaar')
                    ->default(1),
                Toggle::make('is_bundle')
                    ->label('Bundel product')
                    ->helperText('Bestaat dit product uit meerdere andere producten?')
                    ->reactive(),
                Toggle::make('use_bundle_product_price')
                    ->label('Gebruik onderliggend bundel product prijs')
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

                        $record->bundleProducts()->detach($bundleProductIds);
                        $record->bundleProducts()->sync($bundleProductIds);
                    })
                    ->reorderable()
                    ->name('Bundel producten')
                    ->reactive()
                    ->schema([
                        Select::make('bundle_product_id')
                            ->label('Bundel product')
                            ->searchable()
                            ->getSearchResultsUsing(fn (string $query) => Product::isNotBundle()->where('name', 'like', "%{$query}%")->limit(50)->pluck('name', 'id'))
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
                    ->visible(fn (Get $get) => $get('is_bundle')),
            ])
            ->columns(2)
            ->collapsible()
            ->persistCollapsed();

        $newSchema[] = Section::make('Voorraad beheren')->columnSpanFull()
            ->schema(Product::stockFilamentSchema())
            ->columns([
                'default' => 1,
                'lg' => 4,
            ])
            ->hidden(fn ($record, Get $get) => ($record && $record->productGroup->use_parent_stock) || $get('is_bundle'))
            ->persistCollapsed()
            ->collapsible();

        $newSchema[] = Section::make('Praktische informatie beheren')->columnSpanFull()
            ->schema([
                TextInput::make('price')
                    ->label('Prijs van het product')
                    ->helperText('Voorbeeld: 10.25')
                    ->prefix('€')
                    ->minValue(0)
                    ->maxValue(100000)
                    ->required()
                    ->numeric()
                    ->columnSpan([
                        'default' => 1,
                        'lg' => 4,
                    ]),
                TextInput::make('new_price')
                    ->label('Vorige prijs (de hogere prijs)')
                    ->helperText('Voorbeeld: 14.25')
                    ->prefix('€')
                    ->minValue(0)
                    ->maxValue(100000)
                    ->numeric()
                    ->columnSpan([
                        'default' => 1,
                        'lg' => 4,
                    ]),
                TextInput::make('purchase_price')
                    ->label('Inkoop prijs')
                    ->helperText('Voorbeeld: 3.50')
                    ->prefix('€')
                    ->minValue(0)
                    ->maxValue(100000)
                    ->numeric()
                    ->columnSpan([
                        'default' => 1,
                        'lg' => 4,
                    ]),
                TextInput::make('vat_rate')
                    ->label('BTW percentage')
                    ->helperText('21%, 9%, 0% of anders')
                    ->required()
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(100)
                    ->default(21)
                    ->columnSpan([
                        'default' => 1,
                        'lg' => 3,
                    ]),
                TextInput::make('sku')
                    ->label('SKU van het product')
                    ->helperText('Vaak gebruikt voor interne herkenning')
                    ->maxLength(255)
                    ->required()
                    ->default('SKU' . rand(10000, 99999))
                    ->columnSpan([
                        'default' => 1,
                        'lg' => 3,
                    ]),
                TextInput::make('ean')
                    ->label('EAN van het product')
                    ->helperText('Dit is een code die gekoppeld zit aan dit specifieke product')
                    ->maxLength(255)
                    ->columnSpan([
                        'default' => 1,
                        'lg' => 3,
                    ]),
                TextInput::make('article_code')
                    ->label('Artikel code van het product')
                    ->maxLength(255)
                    ->columnSpan([
                        'default' => 1,
                        'lg' => 3,
                    ]),
                TextInput::make('weight')
                    ->label('Gewicht')
                    ->helperText('Berekend in KG')
                    ->maxValue(100000)
                    ->numeric()
                    ->columnSpan([
                        'default' => 1,
                        'lg' => 3,
                    ]),
                TextInput::make('length')
                    ->label('Lengte')
                    ->helperText('Berekend in CM')
                    ->maxValue(100000)
                    ->numeric()
                    ->columnSpan([
                        'default' => 1,
                        'lg' => 3,
                    ]),
                TextInput::make('width')
                    ->label('Breedte')
                    ->helperText('Berekend in CM')
                    ->maxValue(100000)
                    ->numeric()
                    ->columnSpan([
                        'default' => 1,
                        'lg' => 3,
                    ]),
                TextInput::make('height')
                    ->label('Hoogte')
                    ->helperText('Berekend in CM')
                    ->maxValue(100000)
                    ->numeric()
                    ->columnSpan([
                        'default' => 1,
                        'lg' => 3,
                    ]),
            ])
            ->columns([
                'default' => 1,
                'lg' => 12,
            ])
            ->persistCollapsed()
            ->collapsible();

        //                function getFilters($record){
        //                    ray()->count('test');
        //
        //                    return [];
        //                }

        $newSchema[] = Section::make('Filters beheren')->columnSpanFull()
//            ->schema(fn($record) => getFilters($record))
            ->schema(function ($record) {
                $productFilterSchema = [];

                if ($record) {

                    $productFilters = $record->productGroup->activeProductFilters()->with(['productFilterOptions'])->get();
                    $enabledProductFilterOptionIds = $record->productGroup->enabledProductFilterOptions()->pluck('product_filter_option_id')->toArray();

                    foreach ($productFilters as $productFilter) {
                        $productFiltersSchema = [];

                        foreach ($productFilter->productFilterOptions as $productFilterOption) {
                            $productFiltersSchema[] = Checkbox::make("product_filter_{$productFilter->id}_option_{$productFilterOption->id}")
                                ->label("$productFilter->name: $productFilterOption->name")
                                ->visible(fn ($record) => in_array($productFilterOption->id, $enabledProductFilterOptionIds));
                        }

                        $productFilterSchema[] = Section::make("Filter opties voor $productFilter->name")
                            ->schema($productFiltersSchema)
                            ->saveRelationshipsUsing(function ($record, $state) {
                                $record->productFilters()->detach();
                                $productFilters = $record->productGroup->activeProductFilters;

                                foreach ($productFilters as $productFilter) {
                                    foreach ($productFilter->productFilterOptions as $productFilterOption) {
                                        if ($state["product_filter_{$productFilter->id}_option_{$productFilterOption->id}"] ?? false) {
                                            $record->productFilters()->attach($productFilter->id, ['product_filter_option_id' => $productFilterOption->id]);
                                        }
                                    }
                                }
                            })
                            ->collapsible()
                            ->collapsed();
                    }
                }

                return $productFilterSchema;
            })
            ->columns([
                'default' => 1,
                'lg' => 2,
            ])
            ->persistCollapsed()
            ->collapsible()
            ->hidden(fn ($livewire) => $livewire instanceof CreateProduct);

        $productCharacteristics = ProductCharacteristics::orderBy('order', 'ASC')->get();
        $productCharacteristicSchema = [];

        foreach (Locales::getLocales() as $locale) {
            foreach ($productCharacteristics as $productCharacteristic) {
                $productCharacteristicSchema[] = TextInput::make("product_characteristic_{$productCharacteristic->id}_{$locale['id']}")
                    ->label($productCharacteristic->getTranslation('name', $locale['id']) . ' (' . $locale['id'] . ')')
                    ->helperText($productCharacteristic->notes);
            }
        }

        //Not possible in another way because it is filled in pivot table
        $newSchema[] = Section::make('Kenmerken beheren')->columnSpanFull()
            ->schema($productCharacteristicSchema)
            ->columns([
                'default' => 1,
                'lg' => 3,
            ])
            ->persistCollapsed()
            ->collapsed()
            ->hidden(fn ($livewire, Get $get, $record) => $livewire instanceof CreateProduct);

        $newSchema[] = Section::make('Content beheren')
            ->columnSpanFull()
            ->schema(array_merge([
                TextInput::make('name')
                    ->label('Naam')
                    ->maxLength(255)
                    ->required(),
                TextInput::make('slug')
                    ->label('Slug')
                    ->unique('dashed__products', 'slug', fn ($record) => $record)
                    ->helperText('Laat leeg om automatisch te laten genereren'),
                cms()->editorField('description', 'Uitgebreide beschrijving')
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
                Textarea::make('product_search_terms')
                    ->label('Zoekwoorden')
                    ->rows(2)
                    ->helperText('Vul hier termen in waar het product nog meer op gevonden moet kunnen worden')
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
                    ->visible(fn ($livewire) => $livewire instanceof EditProduct)
                    ->helperText('Deze afbeeldingen worden toegevoegd aan de product groep en achter de rest van de afbeeldingen geplaatst. Deze worden opgeslagen in de map: producten')
                    ->image()
                    ->preserveFilenames()
                    ->multiple()
                    ->columnSpanFull(),
                mediaHelper()->field('images', 'Afbeeldingen', required: false, multiple: true, defaultFolder: 'producten')
                    ->columnSpanFull(),
                cms()->getFilamentBuilderBlock(),
            ], static::customBlocksTab('productBlocks')))
            ->collapsible()
            ->persistCollapsed()
            ->columns([
                'default' => 1,
                'lg' => 2,
            ]);

        $newSchema[] = Section::make('Linkjes beheren')->columnSpanFull()
            ->schema([
                Select::make('shippingClasses')
                    ->multiple()
                    ->relationship('shippingClasses', 'name')
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->name)
                    ->label('Link verzendklasses'),
                Select::make('suggestedProducts')
                    ->multiple()
                    ->relationship('suggestedProducts', 'name')
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->nameWithParents)
                    ->label('Link voorgestelde producten'),
                Select::make('crossSellProducts')
                    ->multiple()
                    ->relationship('crossSellProducts', 'name')
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->nameWithParents)
                    ->label('Link cross sell producten')
                    ->helperText('Dit mogen alleen maar producten zijn die zonder verplichte opties zijn'),
                Select::make('globalProductExtras')
                    ->multiple()
                    ->relationship('globalProductExtras', 'name')
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->name)
                    ->label('Link globale product extras'),
                Select::make('globalProductTabs')
                    ->multiple()
                    ->relationship('globalTabs', 'name')
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->name)
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
            ->hidden(fn ($livewire) => $livewire instanceof CreateProduct)
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
            ->hidden(fn ($livewire) => $livewire instanceof CreateProduct)
            ->collapsible()
            ->persistCollapsed();

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
                    ->getStateUsing(fn ($record) => $record->images ? (mediaHelper()->getSingleMedia($record->images[0], 'original')->url ?? '') : ($record->productGroup->images ? (mediaHelper()->getSingleMedia($record->productGroup->images[0], 'original')->url ?? '') : null))
                    ->label(''),
                TextColumn::make('name')
                    ->label('Naam')
                    ->searchable(query: SearchQuery::make())
                    ->sortable(),
                TextColumn::make('total_stock')
                    ->label('Voorraad')
                    ->sortable(),
                TextColumn::make('total_purchases')
                    ->label('Aantal verkopen')
                    ->sortable(),
                IconColumn::make('indexable')
                    ->label('Tonen in overzicht')
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->sortable(),
            ], static::visitableTableColumns()))
            ->reorderable('order')
            ->recordActions([
                EditAction::make()
                    ->button(),
                Action::make('quickActions')
                    ->button()
                    ->label('Snelle acties')
                    ->color('primary')
                    ->modalHeading('Snel bewerken')
                    ->modalSubmitActionLabel('Opslaan')
                    ->fillForm(function (Product $record) {
                        return [
                            'price' => $record->price,
                            'new_price' => $record->new_price,
                            'use_stock' => $record->use_stock,
                            'limit_purchases_per_customer' => $record->limit_purchases_per_customer,
                            'out_of_stock_sellable' => $record->out_of_stock_sellable,
                            'low_stock_notification' => $record->low_stock_notification,
                            'stock' => $record->stock,
                            'expected_in_stock_date' => $record->expected_in_stock_date,
                            'expected_delivery_in_days' => $record->expected_delivery_in_days,
                            'low_stock_notification_limit' => $record->low_stock_notification_limit,
                            'stock_status' => $record->stock_status,
                            'limit_purchases_per_customer_limit' => $record->limit_purchases_per_customer_limit,
                            'fulfillment_provider' => $record->fulfillment_provider,
                        ];
                    })
                    ->schema([
                        Section::make('Beheer de prijzen')
                            ->columnSpanFull()
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
                            ->schema(Product::stockFilamentSchema())
                            ->columns([
                                'default' => 1,
                                'lg' => 2,
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
                    }),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    BulkAction::make('changePrice')
                        ->color('primary')
                        ->label('Verander prijzen')
                        ->schema([
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
                                ->title('De producten zijn aangepast')
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('changePublicStatus')
                        ->color('primary')
                        ->label('Verander publieke status')
                        ->schema([
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
                                ->title('De producten zijn aangepast')
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ])
            ->filters([
                Filter::make('specificProductGroup')
                    ->schema([
                        Select::make('product_group_id')
                            ->label('Product groep')
                            ->multiple()
                            ->options(fn () => ProductGroup::whereHas('products', function ($query) {
                                $query->whereNull('deleted_at');
                            })->pluck('name', 'id')->map(function ($name, $id) {
                                return $name;
                            })->toArray()),
                    ])
                    ->query(function (\Illuminate\Database\Eloquent\Builder $query, array $data): \Illuminate\Database\Eloquent\Builder {
                        if (! $data['product_group_id']) {
                            return $query;
                        }

                        return $query
                            ->whereIn(
                                'product_group_id',
                                $data['product_group_id'],
                            );
                    }),
                Filter::make('categories')
                    ->schema([
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
                Filter::make('indexable')
                    ->schema([
                        Select::make('indexable')
                            ->options([
                                1 => 'Ja',
                                0 => 'Nee',
                            ])
                            ->label('Getoond in overzicht'),
                    ])
                    ->query(function (\Illuminate\Database\Eloquent\Builder $query, array $data): \Illuminate\Database\Eloquent\Builder {
                        if ($data['indexable'] === null || $data['indexable'] === '') {
                            return $query;
                        }

                        return $query->where('indexable', $data['indexable']);
                    }),
            ]);
    }

    public static function getRelations(): array
    {
        return [
//            ChildProductsRelationManager::class,
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
