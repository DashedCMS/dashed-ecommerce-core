<?php

namespace Qubiqx\QcommerceEcommerceCore\Filament\Resources;

use Filament\Resources\Concerns\Translatable;
use Filament\Resources\Form;
use Filament\Resources\Table;
use Filament\Resources\Resource;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Section;
use Filament\Tables\Columns\BooleanColumn;
use Filament\Tables\Columns\TagsColumn;
use Filament\Tables\Columns\TextColumn;
use Qubiqx\QcommerceCore\Classes\Sites;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\MultiSelect;
use Filament\Forms\Components\BelongsToManyMultiSelect;
use Qubiqx\QcommerceEcommerceCore\Filament\Resources\DiscountCodeResource\Pages\EditDiscountCode;
use Qubiqx\QcommerceEcommerceCore\Filament\Resources\DiscountCodeResource\Pages\ListDiscountCodes;
use Qubiqx\QcommerceEcommerceCore\Filament\Resources\DiscountCodeResource\Pages\CreateDiscountCode;
use Qubiqx\QcommerceEcommerceCore\Filament\Resources\ProductCategoryResource\Pages\CreateProductCategory;
use Qubiqx\QcommerceEcommerceCore\Filament\Resources\ProductCharacteristicResource\Pages\CreateProductCharacteristic;
use Qubiqx\QcommerceEcommerceCore\Filament\Resources\ProductCategoryResource\Pages\EditProductCategory;
use Qubiqx\QcommerceEcommerceCore\Filament\Resources\ProductCharacteristicResource\Pages\EditProductCharacteristic;
use Qubiqx\QcommerceEcommerceCore\Filament\Resources\ProductCategoryResource\Pages\ListProductCategory;
use Qubiqx\QcommerceEcommerceCore\Filament\Resources\ProductCharacteristicResource\Pages\ListProductCharacteristic;
use Qubiqx\QcommerceEcommerceCore\Models\ProductCategory;
use Qubiqx\QcommerceEcommerceCore\Models\ProductCharacteristics;

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
                TextInput::make('name')
                    ->label('Naam')
                    ->required()
                    ->maxLength(100)
                    ->rules([
                        'max:100',
                    ]),
                TextInput::make('order')
                    ->label('Volgorde')
                    ->type('number')
                    ->required()
                    ->minLength(1)
                    ->maxLength(100)
                    ->rules([
                        'numeric',
                        'required',
                        'min:1',
                        'max:100',
                    ]),
                Toggle::make('hide_from_public')
                    ->label('Dit kenmerk verbergen op de website')
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
                TextColumn::make('order')
                    ->label('Volgorde')
                    ->searchable()
                    ->sortable(),
                BooleanColumn::make('hide_from_public')
                    ->label('Verberg van website')
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
