<?php

namespace Qubiqx\QcommerceEcommerceCore\Filament\Resources;

use Filament\Forms\Components\Toggle;
use Filament\Resources\Form;
use Filament\Resources\Table;
use Filament\Resources\Resource;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\BooleanColumn;
use Filament\Tables\Columns\TagsColumn;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Qubiqx\QcommerceCore\Classes\Sites;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\MultiSelect;
use Filament\Resources\Concerns\Translatable;
use Filament\Forms\Components\BelongsToSelect;
use Qubiqx\QcommerceEcommerceCore\Filament\Resources\ProductFilterOptionResource\Pages\CreateProductFilterOption;
use Qubiqx\QcommerceEcommerceCore\Filament\Resources\ProductFilterOptionResource\Pages\EditProductFilterOption;
use Qubiqx\QcommerceEcommerceCore\Filament\Resources\ProductFilterOptionResource\Pages\ListProductFilterOption;
use Qubiqx\QcommerceEcommerceCore\Filament\Resources\ProductFilterResource\Pages\CreateProductFilter;
use Qubiqx\QcommerceEcommerceCore\Filament\Resources\ProductFilterResource\Pages\EditProductFilter;
use Qubiqx\QcommerceEcommerceCore\Filament\Resources\ProductFilterResource\Pages\ListProductFilter;
use Qubiqx\QcommerceEcommerceCore\Models\ProductCategory;
use Qubiqx\QcommerceEcommerceCore\Filament\Resources\ProductCategoryResource\Pages\EditProductCategory;
use Qubiqx\QcommerceEcommerceCore\Filament\Resources\ProductCategoryResource\Pages\ListProductCategory;
use Qubiqx\QcommerceEcommerceCore\Filament\Resources\ProductCategoryResource\Pages\CreateProductCategory;
use Qubiqx\QcommerceEcommerceCore\Models\ProductFilter;
use Qubiqx\QcommerceEcommerceCore\Models\ProductFilterOption;

class ProductFilterOptionResource extends Resource
{
    use Translatable;

    protected static ?string $model = ProductFilterOption::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static bool $shouldRegisterNavigation = false;
    protected static ?string $label = 'Product filter optie';
    protected static ?string $pluralLabel = 'Product filter opties';
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
                BelongsToSelect::make('product_filter_id')
                    ->relationship('productFilter', 'name')
                    ->label('Filter')
                    ->required()
                    ->rules([
                        'required',
                    ]),
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
                    ->minValue(1)
                    ->maxValue(10000)
                    ->rules([
                        'required',
                        'numeric',
                        'min:1',
                        'max:10000'
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
                TextColumn::make('productFilter.name')
                    ->label('Filter')
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
            'index' => CreateProductFilterOption::route('/'),
            'create' => CreateProductFilterOption::route('/create'),
            'edit' => EditProductFilterOption::route('/{record}/edit'),
        ];
    }
}
