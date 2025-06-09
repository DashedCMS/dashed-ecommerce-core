<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources;

use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Get;
use Filament\Forms\Form;
use Filament\Forms\Set;
use Filament\Tables\Table;
use Filament\Resources\Resource;
use Filament\Tables\Filters\Filter;
use Dashed\DashedCore\Classes\Sites;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Dashed\DashedCore\Classes\Locales;
use Filament\Forms\Components\Section;
use FilamentTiptapEditor\TiptapEditor;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Actions\DeleteAction;
use Filament\Forms\Components\Placeholder;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Resources\Concerns\Translatable;
use Filament\Tables\Actions\DeleteBulkAction;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Models\ProductExtra;
use Dashed\DashedEcommerceCore\Models\ProductGroup;
use Dashed\DashedEcommerceCore\Models\ProductFilter;
use Dashed\DashedEcommerceCore\Models\ProductCategory;
use Dashed\DashedCore\Classes\QueryHelpers\SearchQuery;
use Dashed\DashedCore\Filament\Concerns\HasVisitableTab;
use Dashed\DashedCore\Filament\Concerns\HasCustomBlocksTab;
use Dashed\DashedEcommerceCore\Models\ProductCharacteristics;
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

    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';
    protected static ?string $navigationGroup = 'Producten';
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

    public static function form(Form $form): Form
    {
        config(['filament-tiptap-editor.directory' => 'dashed/products/images']);

        $schema = [];

        $schema[] = Section::make('Algemene instellingen')
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
                Toggle::make('use_parent_stock')
                    ->label('Gebruik voorraad informatie van deze product groep')
                    ->helperText('Let op: dit is slechts een extra check, de voorraad van het variaties gelden ook')
                    ->default(0)
                    ->reactive(),
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

            $productFilterSchema[] = Section::make("Filter opties voor $productFilter->name")
                ->schema($productFiltersSchema)
                ->collapsible()
                ->persistCollapsed()
                ->columns(2)
                ->visible(fn(Get $get) => in_array($productFilter->id, $get('productFilters')));
        }
        //
        $schema[] = Section::make('Filters beheren')
            ->headerActions([
                \Filament\Forms\Components\Actions\Action::make('createMissingVariations')
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

        $productCharacteristics = ProductCharacteristics::orderBy('order', 'ASC')->get();
        $productCharacteristicSchema = [
            Placeholder::make('product_characteristics')
                ->label('Kenmerken die ingevuld worden bij een variant overschrijven het kenmerk dat je hier invult.')
                ->columnSpanFull(),
        ];

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
            ->persistCollapsed()
            ->collapsed(fn($livewire) => $livewire instanceof EditProductGroup)
            ->hidden(fn($livewire, Get $get, $record) => $livewire instanceof CreateProductGroup || ($get('type') == 'variable' && (!$record && !$get('parent_id') || $record && !$record->parent_id)));

        $schema[] = Section::make('Content beheren')
            ->schema(array_merge([
                TextInput::make('name')
                    ->label('Naam')
                    ->maxLength(255)
                    ->required(),
                TextInput::make('slug')
                    ->label('Slug')
                    ->unique('dashed__product_groups', 'slug', fn($record) => $record)
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
                Textarea::make('short_description')
                    ->label('Korte beschrijving')
                    ->rows(5)
                    ->maxLength(2500),
                Textarea::make('search_terms')
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
                mediaHelper()->field('images', 'Afbeeldingen', required: false, multiple: true)
                    ->helperText('Afbeeldingen van een variant worden VOOR de afbeelding van de product groep getoond'),
                cms()->getFilamentBuilderBlock(),
            ], array_merge(static::customBlocksTab('productBlocks'), static::customBlocksTab('productGroupBlocks'))))
            ->collapsible()
            ->persistCollapsed()
            ->columns([
                'default' => 1,
                'lg' => 2,
            ]);

        $schema[] = Section::make('Linkjes beheren')
            ->schema([
                Select::make('productCategories')
                    ->multiple()
                    ->relationship('productCategories', 'name')
                    ->getOptionLabelFromRecordUsing(fn($record) => $record->nameWithParents)
                    ->label('Link aan categorieeën')
                    ->helperText('Bovenliggende categorieën worden automatisch geactiveerd. Deze categorieen gelden voor alle varianten.'),
                Select::make('suggestedProducts')
                    ->multiple()
                    ->relationship('suggestedProducts', 'name')
                    ->getOptionLabelFromRecordUsing(fn($record) => $record->nameWithParents)
                    ->helperText('Indien je bij een variant ook voorgestelde producten koppelt, worden deze samengevoegd')
                    ->label('Link voorgestelde producten'),
                Select::make('crossSellProducts')
                    ->multiple()
                    ->relationship('crossSellProducts', 'name')
                    ->getOptionLabelFromRecordUsing(fn($record) => $record->nameWithParents)
                    ->label('Link cross sell producten')
                    ->helperText('Dit mogen alleen maar producten zijn die zonder verplichte opties zijn. Indien je bij een variant ook cross sell producten koppelt, worden deze samengevoegd'),
                Select::make('globalProductExtras')
                    ->multiple()
                    ->preload()
                    ->relationship('globalProductExtras', 'name')
                    ->getOptionLabelFromRecordUsing(fn($record) => $record->name)
                    ->helperText('Indien je bij een variant ook product extras koppelt, worden deze samengevoegd')
                    ->label('Link globale product extras'),
                Select::make('globalProductTabs')
                    ->multiple()
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

        $schema[] = Section::make('Product extras')
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

        $schema[] = Section::make('Product tabs')
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
                        TiptapEditor::make('content')
                            ->label('Content')
                            ->required(),
                    ]),
            ])
            ->hidden(fn($livewire) => $livewire instanceof CreateProductGroup)
            ->collapsible()
            ->persistCollapsed();

        $schema[] = Section::make('Volume korting')
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

        $schema[] = Section::make('Meta data')
            ->schema(static::metadataTab())
            ->collapsible()
            ->persistCollapsed();

        return $form->schema($schema);
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
            ->actions([
                EditAction::make()
                    ->button(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->filters([
                Filter::make('categories')
                    ->form([
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
