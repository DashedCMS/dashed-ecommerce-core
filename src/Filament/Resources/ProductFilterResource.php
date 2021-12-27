<?php

namespace Qubiqx\QcommerceEcommerceCore\Filament\Resources;

use Filament\Resources\Form;
use Filament\Resources\Table;
use Filament\Resources\Resource;
use Filament\Forms\Components\Toggle;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\BooleanColumn;
use Filament\Resources\Concerns\Translatable;
use Qubiqx\QcommerceEcommerceCore\Models\ProductFilter;
use Qubiqx\QcommerceEcommerceCore\Filament\Resources\ProductFilterResource\Pages\EditProductFilter;
use Qubiqx\QcommerceEcommerceCore\Filament\Resources\ProductFilterResource\Pages\ListProductFilter;
<<<<<<< HEAD
use Qubiqx\QcommerceEcommerceCore\Filament\Resources\ProductFilterResource\RelationManagers\ProductFilterOptionRelationManager;
use Qubiqx\QcommerceEcommerceCore\Models\ProductCategory;
use Qubiqx\QcommerceEcommerceCore\Filament\Resources\ProductCategoryResource\Pages\EditProductCategory;
use Qubiqx\QcommerceEcommerceCore\Filament\Resources\ProductCategoryResource\Pages\ListProductCategory;
use Qubiqx\QcommerceEcommerceCore\Filament\Resources\ProductCategoryResource\Pages\CreateProductCategory;
use Qubiqx\QcommerceEcommerceCore\Models\ProductFilter;
=======
use Qubiqx\QcommerceEcommerceCore\Filament\Resources\ProductFilterResource\Pages\CreateProductFilter;
>>>>>>> 1c007ce87058a3f30d645f2e34142d5c3d31711f

class ProductFilterResource extends Resource
{
    use Translatable;

    protected static ?string $model = ProductFilter::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';
    protected static ?string $navigationGroup = 'E-commerce';
    protected static ?string $navigationLabel = 'Product filters';
    protected static ?string $label = 'Product filter';
    protected static ?string $pluralLabel = 'Product filters';
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
                Toggle::make('hide_filter_on_overview_page')
                    ->label('Moet deze filter verborgen worden op de overzichts pagina van de producten?'),
                TextInput::make('name')
                    ->label('Naam')
                    ->required()
                    ->maxLength(100)
                    ->rules([
                        'max:100',
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
                BooleanColumn::make('hide_filter_on_overview_page')
                    ->label('Verbergen op website')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('product_filter_values_amount')
                    ->label('Aantal waardes')
                    ->sortable()
                    ->searchable()
                    ->getStateUsing(fn ($record) => $record->productFilterOptions->count()),
            ])
            ->filters([
                //
            ]);
    }

    public static function getRelations(): array
    {
        return [
            ProductFilterOptionRelationManager::class
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProductFilter::route('/'),
            'create' => CreateProductFilter::route('/create'),
            'edit' => EditProductFilter::route('/{record}/edit'),
        ];
    }
}
