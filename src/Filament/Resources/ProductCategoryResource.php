<?php

namespace Qubiqx\QcommerceEcommerceCore\Filament\Resources;

use Filament\Resources\Form;
use Filament\Resources\Table;
use Filament\Resources\Resource;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Section;
use Filament\Tables\Columns\TagsColumn;
use Filament\Tables\Columns\TextColumn;
use Qubiqx\QcommerceCore\Classes\Sites;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\FileUpload;
use Filament\Resources\Concerns\Translatable;
use Qubiqx\QcommerceEcommerceCore\Models\ProductCategory;
use Qubiqx\QcommerceCore\Filament\Concerns\HasMetadataTab;
use Qubiqx\QcommerceEcommerceCore\Filament\Resources\ProductCategoryResource\Pages\EditProductCategory;
use Qubiqx\QcommerceEcommerceCore\Filament\Resources\ProductCategoryResource\Pages\ListProductCategory;
use Qubiqx\QcommerceEcommerceCore\Filament\Resources\ProductCategoryResource\Pages\CreateProductCategory;

class ProductCategoryResource extends Resource
{
    use Translatable;
    use HasMetadataTab;

    protected static ?string $model = ProductCategory::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';
    protected static ?string $navigationGroup = 'E-commerce';
    protected static ?string $navigationLabel = 'Product categorieën';
    protected static ?string $label = 'Product categorie';
    protected static ?string $pluralLabel = 'Product categorieën';
    protected static ?int $navigationSort = 3;

    public static function getGloballySearchableAttributes(): array
    {
        return [
            'name',
        ];
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Grid::make([
                    'default' => 1,
                    'sm' => 1,
                    'md' => 1,
                    'lg' => 1,
                    'xl' => 6,
                    '2xl' => 6,
                ])->schema([
                    Section::make('Content')
                        ->schema([
                            Select::make('site_ids')
                                ->multiple()
                                ->label('Actief op sites')
                                ->options(collect(Sites::getSites())->pluck('name', 'id')->toArray())
                                ->hidden(function () {
                                    return ! (Sites::getAmountOfSites() > 1);
                                })
                                ->required()
                                ->columnSpan([
                                    'default' => 2,
                                    'sm' => 2,
                                    'md' => 2,
                                    'lg' => 2,
                                    'xl' => 2,
                                    '2xl' => 2,
                                ]),
                            Select::make('parent_category_id')
                                ->options(fn ($record) => ProductCategory::where('id', '!=', $record->id ?? 0)->pluck('name', 'id'))
                                ->searchable()
                                ->label('Bovenliggende product categorie')
                                ->columnSpan([
                                    'default' => 2,
                                    'sm' => 2,
                                    'md' => 2,
                                    'lg' => 2,
                                    'xl' => 2,
                                    '2xl' => 2,
                                ]),
                            TextInput::make('name')
                                ->label('Naam')
                                ->required()
                                ->maxLength(100)
                                ->rules([
                                    'max:100',
                                ])
                                ->columnSpan([
                                    'default' => 2,
                                    'sm' => 2,
                                    'md' => 2,
                                    'lg' => 2,
                                    'xl' => 1,
                                    '2xl' => 1,
                                ]),
                            TextInput::make('slug')
                                ->label('Slug')
                                ->unique('qcommerce__product_categories', 'slug', fn ($record) => $record)
                                ->helperText('Laat leeg om automatisch te laten genereren')
                                ->rules([
                                    'max:255',
                                ])
                                ->columnSpan([
                                    'default' => 2,
                                    'sm' => 2,
                                    'md' => 2,
                                    'lg' => 2,
                                    'xl' => 1,
                                    '2xl' => 1,
                                ]),
                            FileUpload::make('image')
                                ->directory('qcommerce/product-categories/images')
                                ->name('Afbeelding')
                                ->image()
                                ->columnSpan([
                                    'default' => 2,
                                    'sm' => 2,
                                    'md' => 2,
                                    'lg' => 2,
                                    'xl' => 2,
                                    '2xl' => 2,
                                ]),
                            Builder::make('content')
                                ->blocks(cms()->builder('blocks'))
                                ->columnSpan([
                                    'default' => 1,
                                    'lg' => 1,
                                ])
                                ->columnSpan([
                                    'default' => 2,
                                    'sm' => 2,
                                    'md' => 2,
                                    'lg' => 2,
                                    'xl' => 2,
                                    '2xl' => 2,
                                ]),
                            ])
                        ->columns(2)
                        ->columnSpan([
                            'default' => 1,
                            'sm' => 1,
                            'md' => 1,
                            'lg' => 1,
                            'xl' => 4,
                            '2xl' => 4,]),
                    Grid::make([
                        'default' => 1,
                        'sm' => 1,
                        'md' => 1,
                        'lg' => 1,
                        'xl' => 2,
                        '2xl' => 2,
                    ])->schema([
                        Section::make('Meta Data')
                            ->schema(static::metadataTab())
                            ->columnSpan([
                                'default' => 1,
                                'sm' => 1,
                                'md' => 1,
                                'lg' => 1,
                                'xl' => 2,
                                '2xl' => 2,
                            ]),
                    ])
                        ->columnSpan([
                            'default' => 1,
                            'sm' => 1,
                            'md' => 1,
                            'lg' => 1,
                            'xl' => 2,
                            '2xl' => 2,
                        ]),
                ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Naam')
                    ->searchable(),
                TagsColumn::make('site_ids')
                    ->label('Actief op site(s)')
                    ->sortable()
                    ->hidden(! (Sites::getAmountOfSites() > 1)),
                TextColumn::make('parentProductCategory.name')
                    ->label('Bovenliggende categorie')
                    ->sortable(),
            ])
            ->filters([
                //
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
            'index' => ListProductCategory::route('/'),
            'create' => CreateProductCategory::route('/create'),
            'edit' => EditProductCategory::route('/{record}/edit'),
        ];
    }
}
