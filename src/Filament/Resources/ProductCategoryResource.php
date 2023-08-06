<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources;

use Filament\Resources\Form;
use Filament\Resources\Table;
use Filament\Resources\Resource;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Section;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\FileUpload;
use Filament\Resources\Concerns\Translatable;
use Dashed\DashedEcommerceCore\Models\ProductCategory;
use Dashed\DashedCore\Filament\Concerns\HasVisitableTab;
use Dashed\DashedEcommerceCore\Filament\Resources\ProductCategoryResource\Pages\EditProductCategory;
use Dashed\DashedEcommerceCore\Filament\Resources\ProductCategoryResource\Pages\ListProductCategory;
use Dashed\DashedEcommerceCore\Filament\Resources\ProductCategoryResource\Pages\CreateProductCategory;

class ProductCategoryResource extends Resource
{
    use Translatable;
    use HasVisitableTab;

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
                                ->unique('dashed__product_categories', 'slug', fn ($record) => $record)
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
                                ->directory('dashed/product-categories/images')
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
                        Section::make('Algemene informatie')
                            ->schema(static::publishTab())
                            ->columnSpan([
                                'default' => 1,
                                'sm' => 1,
                                'md' => 1,
                                'lg' => 1,
                                'xl' => 2,
                                '2xl' => 2,
                            ]),
                        Section::make('Meta data')
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
            ->columns(array_merge([
                TextColumn::make('name')
                    ->label('Naam')
                    ->searchable(),
            ], static::visitableTableColumns()))
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
