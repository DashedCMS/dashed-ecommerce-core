<?php

namespace Qubiqx\QcommerceEcommerceCore\Filament\Resources;

use Filament\Resources\Form;
use Filament\Resources\Table;
use Filament\Resources\Resource;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\TagsColumn;
use Filament\Tables\Columns\TextColumn;
use Qubiqx\QcommerceCore\Classes\Sites;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\MultiSelect;
use Filament\Tables\Columns\BooleanColumn;
use Filament\Resources\Concerns\Translatable;
use Filament\Forms\Components\HasManyRepeater;
use Qubiqx\QcommerceEcommerceCore\Models\Product;
use Qubiqx\QcommerceEcommerceCore\Models\ProductExtra;
use Filament\Forms\Components\BelongsToManyMultiSelect;
use Qubiqx\QcommerceEcommerceCore\Models\ProductFilter;
use Qubiqx\QcommerceEcommerceCore\Models\ProductExtraOption;
use Qubiqx\QcommerceEcommerceCore\Models\ProductCharacteristics;
use Qubiqx\QcommerceEcommerceCore\Filament\Resources\ProductResource\Pages\EditProduct;
use Qubiqx\QcommerceEcommerceCore\Filament\Resources\ProductResource\Pages\ListProducts;
use Qubiqx\QcommerceEcommerceCore\Filament\Resources\ProductResource\Pages\CreateProduct;
use Qubiqx\QcommerceEcommerceCore\Filament\Resources\ProductResource\RelationManagers\ChildProductsRelationManager;

class ProductResource extends Resource
{
    use Translatable;

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
            'meta_title',
            'meta_description',
        ];
    }

    public static function form(Form $form): Form
    {
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
                    ->hidden(fn ($record) => $record && $record->parent_product_id),
                Select::make('parent_product_id')
                    ->label('Bovenliggende product')
                    ->options(Product::where('type', 'variable')->whereNull('parent_product_id')->pluck('name', 'id'))
                    ->reactive()
                    ->searchable()
                    ->hidden(fn (\Closure $get, $livewire) => $get('type') != 'variable' || $livewire instanceof EditProduct),
                MultiSelect::make('site_ids')
                    ->label('Actief op sites')
                    ->options(collect(Sites::getSites())->pluck('name', 'id')->toArray())
                    ->default([Sites::getFirstSite()['id']])
                    ->hidden(fn (\Closure $get) => ! (Sites::getAmountOfSites() > 1) || $get('parent_product_id') && $get('type') == 'variable')
                    ->required()
                    ->disabled(fn ($record) => $record && $record->parent_product_id),
                Toggle::make('public')
                    ->label('Openbaar')
                    ->default(1),
                Toggle::make('only_show_parent_product')
                    ->label('Toon 1 variatie op overzichtspagina')
                    ->hidden(fn ($record, \Closure $get) => $get('type') != 'variable' || ($record && $record->parent_product_id)),
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
                    ->maxValue(100000)
                    ->required()
                    ->rules([
                        'required',
                        'numeric',
                        'max:100000',
                    ])
                    ->hidden(fn (\Closure $get) => ! $get('use_stock')),
                Toggle::make('out_of_stock_sellable')
                    ->label('Product doorverkopen wanneer niet meer op voorraad (pre-orders)')
                    ->reactive()
                    ->hidden(fn (\Closure $get) => ! $get('use_stock')),
                DatePicker::make('expected_in_stock_date')
                    ->label('Wanneer komt dit product weer op voorraad')
                    ->reactive()
                    ->required()
                    ->hidden(fn (\Closure $get) => ! $get('use_stock') || ! $get('out_of_stock_sellable')),
                Toggle::make('low_stock_notification')
                    ->label('Ik wil een melding krijgen als dit product laag op voorraad raakt')
                    ->reactive()
                    ->hidden(fn (\Closure $get) => ! $get('use_stock')),
                TextInput::make('low_stock_notification_limit')
                    ->label('Als de voorraad van dit product onder onderstaand nummer komt, krijg je een notificatie')
                    ->type('number')
                    ->reactive()
                    ->required()
                    ->minValue(1)
                    ->maxValue(100000)
                    ->default(1)
                    ->required()
                    ->rules([
                        'required',
                        'numeric',
                        'min:1',
                        'max:100000',
                    ])
                    ->hidden(fn (\Closure $get) => ! $get('use_stock') || ! $get('low_stock_notification')),
                Select::make('stock_status')
                    ->label('Is dit product op voorraad')
                    ->options([
                        'in_stock' => 'Op voorraad',
                        'out_of_stock' => 'Uitverkocht',
                    ])
                    ->default('in_stock')
                    ->required()
                    ->rules([
                        'required',
                    ])
                    ->hidden(fn (\Closure $get) => $get('use_stock')),
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
                    ->rules([
                        'required',
                        'numeric',
                        'min:1',
                        'max:100000',
                    ])
                    ->hidden(fn (\Closure $get) => ! $get('limit_purchases_per_customer')),
            ])
            ->hidden(fn ($record, \Closure $get) => $get('type') == 'variable' && (! $record && ! $get('parent_product_id') || $record && ! $record->parent_product_id))
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
                ->hidden(fn ($record, \Closure $get) => $get('type') == 'variable' && $record && $record->parent_product_id);
            $productFilterSchema[] = Toggle::make("product_filter_{$productFilter->id}_use_for_variations")
                ->label("$productFilter->name gebruiken voor variaties op de product pagina")
                ->hidden(fn (\Closure $get) => ! $get("product_filter_$productFilter->id"))
                ->columnSpan([
                    'default' => 1,
                    'lg' => 2,
                ])
                ->hidden(fn ($record, \Closure $get) => $get('type') == 'variable' && $record && $record->parent_product_id);
            foreach ($productFilter->productFilterOptions as $productFilterOption) {
                $productFiltersSchema[] = Checkbox::make("product_filter_{$productFilter->id}_option_{$productFilterOption->id}")
                    ->label("$productFilter->name: $productFilterOption->name");
            }
            $productFilterSchema[] = Section::make("Filter opties voor $productFilter->name")
                ->schema($productFiltersSchema)
                ->collapsible()
                ->collapsed()
                ->hidden(fn (\Closure $get, $record) => ! $get("product_filter_$productFilter->id") || ($get('type') == 'variable' && $record && ! $record->parent_product_id));
        }

        $schema[] = Section::make('Filters beheren')
            ->schema($productFilterSchema)
            ->columns([
                'default' => 1,
                'lg' => 2,
            ])
            ->hidden(fn ($livewire) => $livewire instanceof CreateProduct)
            ->collapsed(fn ($livewire) => $livewire instanceof EditProduct);

        $productCharacteristics = ProductCharacteristics::orderBy('order', 'ASC')->get();
        $productCharacteristicSchema = [];

        foreach ($productCharacteristics as $productCharacteristic) {
            $productCharacteristicSchema[] = TextInput::make("product_characteristic_$productCharacteristic->id")
                ->label($productCharacteristic->name);
        }

        $schema[] = Section::make('Kenmerken beheren')
            ->schema($productCharacteristicSchema)
            ->columns([
                'default' => 1,
                'lg' => 3,
            ])
            ->hidden(fn ($livewire, \Closure $get, $record) => $livewire instanceof CreateProduct || ($get('type') == 'variable' && (! $record && ! $get('parent_product_id') || $record && ! $record->parent_product_id)))
            ->collapsed(fn ($livewire) => $livewire instanceof EditProduct);

        $schema[] = Section::make('Content beheren')
            ->schema([TextInput::make('name')
                ->label('Naam')
                ->maxLength(255)
                ->required()
                ->rules(['required',
                    'max:255',]),
                TextInput::make('slug')
                    ->label('Slug')
                    ->unique('qcommerce__products', 'slug', fn ($record) => $record)
                    ->helperText('Laat leeg om automatisch te laten genereren')
                    ->rules(['max:255',]),
                RichEditor::make('description')
                    ->label('Uitgebreide beschrijving')
                    ->fileAttachmentsDirectory('/qcommerce/products/images')
                    ->toolbarButtons(['attachFiles',
                        'blockquote',
                        'bold',
                        'bulletList',
                        'codeBlock',
                        'h2',
                        'h3',
                        'h4',
                        'italic',
                        'link',
                        'orderedList',
                        'redo',
                        'strike',
                        'undo',])
                    ->rules(['max:10000',])
                    ->columnSpan(['default' => 1,
                        'lg' => 2,])
                    ->hidden(fn ($record, \Closure $get) => $get('type') == 'variable' && (! $record && ! $get('parent_product_id') || $record && ! $record->parent_product_id)),
                Textarea::make('short_description')
                    ->label('Korte beschrijving')
                    ->rows(5)
                    ->maxLength(2500)
                    ->rules(['max:2500',])
                    ->hidden(fn ($record, \Closure $get) => $get('type') == 'variable' && (! $record && ! $get('parent_product_id') || $record && ! $record->parent_product_id)),
                Textarea::make('search_terms')
                    ->label('Zoekwoorden')
                    ->rows(2)
                    ->helperText('Vul hier termen in waar het product nog meer op gevonden moet kunnen worden')
                    ->maxLength(2500)
                    ->rules(['max:2500',])
                    ->hidden(fn ($record, \Closure $get) => $get('type') == 'variable' && (! $record && ! $get('parent_product_id') || $record && ! $record->parent_product_id)),
                TextInput::make('meta_title')
                    ->label('Meta titel')
                    ->maxLength(100)
                    ->rules(['max:100',])
                    ->hidden(fn ($record, \Closure $get) => $get('type') == 'variable' && (! $record && ! $get('parent_product_id') || $record && ! $record->parent_product_id)),
                Textarea::make('meta_description')
                    ->label('Meta descriptie')
                    ->rows(2)
                    ->maxLength(200)
                    ->rules(['max:200',])
                    ->hidden(fn ($record, \Closure $get) => $get('type') == 'variable' && (! $record && ! $get('parent_product_id') || $record && ! $record->parent_product_id)),
                FileUpload::make('meta_image')
                    ->directory('qcommerce/products/meta-images')
                    ->name('Meta afbeelding')
                    ->image()
                    ->hidden(fn ($record, \Closure $get) => $get('type') == 'variable' && (! $record && ! $get('parent_product_id') || $record && ! $record->parent_product_id)),
                TextInput::make('order')
                    ->label('Volgorde')
                    ->required()
                    ->type('number')
                    ->minValue(1)
                    ->maxValue(100000)
                    ->default(1)
                    ->rules(['numeric',
                        'required',
                        'min:1',
                        'max:100000',]),
                Builder::make('content')
                    ->blocks(cms()->builder('blocks'))
                    ->columnSpan(['default' => 1,
                        'lg' => 2,])
                    ->hidden(fn ($record, \Closure $get) => $get('type') == 'variable' && (! $record && ! $get('parent_product_id') || $record && ! $record->parent_product_id)),])
            ->columns(['default' => 1,
                'lg' => 2,])
            ->collapsed(fn ($livewire) => $livewire instanceof EditProduct);

        $schema[] = Section::make('Afbeeldingen beheren')
            ->schema([
                Repeater::make('images')
                    ->schema([
                        FileUpload::make('image')
                            ->directory('qcommerce/products/images')
                            ->name('Afbeelding')
                            ->image(),
                        TextInput::make('alt_text')
                            ->label('Alt tekst')
                            ->maxLength(1000)
                            ->rules([
                                'max:1000',
                            ]),
//                        TextInput::make('order')
//                            ->label('Volgorde')
//                            ->type('number')
//                            ->minValue(1)
//                            ->maxValue(100000)
//                            ->default(1)
//                            ->required()
//                            ->rules([
//                                'required',
//                                'numeric',
//                                'min:1',
//                                'max:100000',
//                            ]),
                    ])
                    ->createItemButtonLabel('Nieuwe afbeelding toevoegen'),
            ])
            ->hidden(fn ($record, \Closure $get) => $get('type') == 'variable' && (! $record && ! $get('parent_product_id') || $record && ! $record->parent_product_id))
            ->collapsible();

        $schema[] = Section::make('Praktische informatie beheren')
            ->schema([
                TextInput::make('price')
                    ->label('Prijs van het product')
                    ->helperText('Voorbeeld: 10.25')
                    ->prefix('€')
                    ->minValue(1)
                    ->maxValue(100000)
                    ->required()
                    ->rules(['required',
                        'numeric',
                        'min:1',
                        'max:100000',
                    ]),
                TextInput::make('new_price')
                    ->label('Vorige prijs (de hogere prijs)')
                    ->helperText('Voorbeeld: 14.25')
                    ->prefix('€')
                    ->minValue(1)
                    ->maxValue(100000)
                    ->rules(['numeric',
                        'min:1',
                        'max:100000',
                    ]),
                TextInput::make('vat_rate')
                    ->label('BTW percentage')
                    ->helperText('21%, 9%, 0% of anders')
                    ->required()
                    ->rules(['numeric',
                        'min:1',
                        'max:100',
                        'required',])
                    ->default(21),
                TextInput::make('sku')
                    ->label('SKU van het product')
                    ->helperText('Vaak gebruikt voor interne herkenning')
                    ->maxLength(255)
                    ->required()
                    ->rules(['required',
                        'max:255',]),
                TextInput::make('ean')
                    ->label('EAN van het product')
                    ->helperText('Dit is een code die gekoppeld zit aan dit specifieke product')
                    ->maxLength(255)
                    ->rules(['max:255',]),
                TextInput::make('weight')
                    ->label('Gewicht')
                    ->helperText('Berekend in KG')
                    ->maxLength(255)
                    ->rules(['max:255',]),
                TextInput::make('length')
                    ->label('Lengte')
                    ->helperText('Berekend in CM')
                    ->maxLength(255)
                    ->rules(['max:255',]),
                TextInput::make('width')
                    ->label('Breedte')
                    ->helperText('Berekend in CM')
                    ->maxLength(255)
                    ->rules(['max:255',]),
                TextInput::make('height')
                    ->label('Hoogte')
                    ->helperText('Berekend in CM')
                    ->maxLength(255)
                    ->rules(['max:255',]),])
            ->columns(['default' => 1,
                'lg' => 2,])
            ->hidden(fn ($record, \Closure $get) => $get('type') == 'variable' && (! $record && ! $get('parent_product_id') || $record && ! $record->parent_product_id))
            ->collapsible();

        $schema[] = Section::make('Linkjes beheren')
            ->schema([
                BelongsToManyMultiSelect::make('productCategories')
                    ->preload()
                    ->relationship('productCategories', 'name')
                    ->label('Link aan categorieeën')
                    ->helperText('Bovenliggende categorieën worden automatisch geactiveerd'),
                BelongsToManyMultiSelect::make('shippingClasses')
                    ->preload()
                    ->relationship('shippingClasses', 'name')
                    ->label('Link verzendklasses'),
                BelongsToManyMultiSelect::make('suggestedProducts')
                    ->preload()
                    ->relationship('suggestedProducts', 'name')
                    ->label('Link voorgestelde producten'),])
            ->columns([
                'default' => 1,
                'lg' => 2,
            ])
            ->hidden(fn ($record, \Closure $get) => $get('type') == 'variable' && (! $record && ! $get('parent_product_id') || $record && ! $record->parent_product_id))
            ->collapsible();

        $schema[] = Section::make('Product extras')
            ->schema([
                HasManyRepeater::make('productExtras')
                    ->relationship('productExtras')
                    ->saveRelationshipsUsing(function ($record, $state, $livewire, HasManyRepeater $component) {
                        $relationship = $component->getRelationship();
                        $state = $component->getState();

                        foreach ($relationship->pluck($relationship->getLocalKeyName()) as $keyToCheckForDeletion) {
                            if (array_key_exists($keyToCheckForDeletion, $state)) {
                                continue;
                            }

                            $relationship->find($keyToCheckForDeletion)?->delete();
                        }

                        $childComponentContainers = $component->getChildComponentContainers();

                        foreach ($state as $itemKey => &$itemData) {
                            if ($record = ProductExtra::find($itemKey)) {
                                $name = json_decode($record->getRawOriginal('name'), true);
                                $name[$livewire->activeFormLocale] = $itemData['name'];
                                $itemData['name'] = $name;
                                $record->update($itemData);

                                continue;
                            }

                            $name = [];
                            $name[$livewire->activeFormLocale] = $itemData['name'];
                            $itemData['name'] = $name;
                            $record = $relationship->create($itemData);
                            $childComponentContainers[$itemKey]->model($record)->saveRelationships();
                        }
                    })
                    ->schema([
                        TextInput::make('name')
                            ->label('Naam')
                            ->required()
                            ->maxLength(255)
                            ->rules([
                                'required',
                                'max:255',
                            ]),
                        Toggle::make('required')
                            ->label('Verplicht'),
                        Select::make('type')
                            ->label('Naam')
                            ->options([
                                'single' => '1 optie',
                                'multiple' => 'Meerdere opties (mogelijk nog niet ondersteund door jouw webshop)',
                            ])
                            ->default('single')
                            ->required()
                            ->rules([
                                'required',
                            ]),
                        HasManyRepeater::make('productExtra.productExtraOptions')
                            ->relationship('productExtraOptions')
                            ->saveRelationshipsUsing(function ($record, $state, $livewire, HasManyRepeater $component) {
                                $relationship = $component->getRelationship();
                                $state = $component->getState();

                                foreach ($relationship->pluck($relationship->getLocalKeyName()) as $keyToCheckForDeletion) {
                                    if (array_key_exists($keyToCheckForDeletion, $state)) {
                                        continue;
                                    }

                                    $relationship->find($keyToCheckForDeletion)?->delete();
                                }

                                $childComponentContainers = $component->getChildComponentContainers();

                                foreach ($state as $itemKey => &$itemData) {
                                    if ($record = ProductExtraOption::find($itemKey)) {
                                        $value = json_decode($record->getRawOriginal('value'), true);
                                        $value[$livewire->activeFormLocale] = $itemData['value'];
                                        $itemData['value'] = $value;
                                        $record->update($itemData);

                                        continue;
                                    }

                                    $value = [];
                                    $value[$livewire->activeFormLocale] = $itemData['value'];
                                    $itemData['value'] = $value;
                                    $record = $relationship->create($itemData);
                                    $childComponentContainers[$itemKey]->model($record)->saveRelationships();
                                }
                            })
                            ->schema([
                                TextInput::make('value')
                                    ->label('Waarde')
                                    ->required()
                                    ->maxLength(255)
                                    ->rules([
                                        'required',
                                        'max:255',
                                    ]),
                                TextInput::make('price')
                                    ->label('Meerprijs van deze optie')
                                    ->prefix('€')
                                    ->helperText('Voorbeeld: 10.25')
                                    ->rules([
                                        'numeric',
                                        'min:0.00',
                                        'max:10000',
                                    ]),
                                Toggle::make('calculate_only_1_quantity')
                                    ->label('Deze extra maar 1x meetellen, ook al worden er meerdere van het product gekocht'),
                            ]),
                    ]),
            ])
            ->hidden(fn ($livewire) => $livewire instanceof CreateProduct)
            ->collapsible();

        return $form->schema($schema);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Naam')
                    ->searchable([
                        'site_ids',
                        'name',
                        'slug',
                        'short_description',
                        'description',
                        'search_terms',
                        'sku',
                        'ean',
                        'content',
                        'meta_title',
                        'meta_description',
                    ])
                    ->sortable(),
                TagsColumn::make('site_ids')
                    ->label('Actief op site(s)')
                    ->sortable()
                    ->hidden(! (Sites::getAmountOfSites() > 1))
                    ->searchable(),
                TextColumn::make('total_purchases')
                    ->label('Aantal verkopen'),
                BooleanColumn::make('status')
                    ->label('Status'),
            ])
            ->filters([
                //
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
