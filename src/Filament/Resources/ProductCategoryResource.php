<?php

namespace Qubiqx\QcommerceEcommerceCore\Filament\Resources;

use Filament\Resources\Form;
use Filament\Resources\Table;
use Filament\Resources\Resource;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\TagsColumn;
use Filament\Tables\Columns\TextColumn;
use Qubiqx\QcommerceCore\Classes\Sites;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\MultiSelect;
use Filament\Resources\Concerns\Translatable;
use Filament\Forms\Components\BelongsToSelect;
use Qubiqx\QcommerceEcommerceCore\Models\ProductCategory;
use Qubiqx\QcommerceEcommerceCore\Filament\Resources\ProductCategoryResource\Pages\EditProductCategory;
use Qubiqx\QcommerceEcommerceCore\Filament\Resources\ProductCategoryResource\Pages\ListProductCategory;
use Qubiqx\QcommerceEcommerceCore\Filament\Resources\ProductCategoryResource\Pages\CreateProductCategory;

class ProductCategoryResource extends Resource
{
    use Translatable;

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
                MultiSelect::make('site_ids')
                    ->label('Actief op sites')
                    ->options(collect(Sites::getSites())->pluck('name', 'id')->toArray())
                    ->hidden(function () {
                        return ! (Sites::getAmountOfSites() > 1);
                    })
                    ->required(),
                BelongsToSelect::make('parent_product_category_id')
                    ->relationship('parentProductCategory', 'name')
                    ->label('Bovenliggende product categorie'),
                TextInput::make('name')
                    ->label('Naam')
                    ->required()
                    ->maxLength(100)
                    ->rules([
                        'max:100',
                    ]),
                TextInput::make('slug')
                    ->label('Slug')
                    ->unique('qcommerce__product_categories', 'slug', fn ($record) => $record)
                    ->helperText('Laat leeg om automatisch te laten genereren')
                    ->required()
                    ->rules([
                        'max:255',
                    ]),
                FileUpload::make('image')
                    ->directory('qcommerce/product-categories/images')
                    ->name('Afbeelding')
                    ->image(),
                TextInput::make('meta_title')
                    ->label('Meta title')
                    ->rules([
                        'nullable',
                        'min:5',
                        'max:60',
                    ]),
                Textarea::make('meta_description')
                    ->label('Meta descriptie')
                    ->rows(2)
                    ->rules([
                        'nullable',
                        'min:5',
                        'max:158',
                    ]),
                FileUpload::make('meta_image')
                    ->directory('qcommerce/product-categories/meta-images')
                    ->name('Meta afbeelding')
                    ->image(),
                Builder::make('content')
                    ->blocks(cms()->builder('blocks'))
                    ->columnSpan([
                        'default' => 1,
                        'lg' => 2,
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Naam')
                    ->searchable()
                    ->sortable(),
                TagsColumn::make('site_ids')
                    ->label('Actief op site(s)')
                    ->sortable()
                    ->hidden(! (Sites::getAmountOfSites() > 1))
                    ->searchable(),
                TextColumn::make('parentProductCategory.name')
                    ->label('Bovenliggende category')
                    ->sortable()
                    ->searchable(),
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
