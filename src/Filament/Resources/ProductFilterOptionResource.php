<?php

namespace Qubiqx\QcommerceEcommerceCore\Filament\Resources;

use Filament\Resources\Form;
use Filament\Resources\Table;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Concerns\Translatable;
use Filament\Forms\Components\BelongsToSelect;
use Qubiqx\QcommerceEcommerceCore\Models\ProductFilterOption;
use Qubiqx\QcommerceEcommerceCore\Filament\Resources\ProductFilterOptionResource\Pages\EditProductFilterOption;
use Qubiqx\QcommerceEcommerceCore\Filament\Resources\ProductFilterOptionResource\Pages\CreateProductFilterOption;

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
                        'max:10000',
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
