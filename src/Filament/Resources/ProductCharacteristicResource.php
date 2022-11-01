<?php

namespace Qubiqx\QcommerceEcommerceCore\Filament\Resources;

use Filament\Resources\Form;
use Filament\Resources\Table;
use Filament\Resources\Resource;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Section;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\BooleanColumn;
use Filament\Resources\Concerns\Translatable;
use Qubiqx\QcommerceEcommerceCore\Models\ProductCharacteristics;
use Qubiqx\QcommerceEcommerceCore\Filament\Resources\ProductCharacteristicResource\Pages\EditProductCharacteristic;
use Qubiqx\QcommerceEcommerceCore\Filament\Resources\ProductCharacteristicResource\Pages\ListProductCharacteristic;
use Qubiqx\QcommerceEcommerceCore\Filament\Resources\ProductCharacteristicResource\Pages\CreateProductCharacteristic;

class ProductCharacteristicResource extends Resource
{
    use Translatable;

    protected static ?string $model = ProductCharacteristics::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';
    protected static ?string $navigationGroup = 'E-commerce';
    protected static ?string $navigationLabel = 'Product kenmerken';
    protected static ?string $label = 'Product kenmerk';
    protected static ?string $pluralLabel = 'Product kenmerken';
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
            ->schema(
                [
                    Grid::make([
                        'default' => 1,
                        'sm' => 1,
                        'md' => 1,
                        'lg' => 1,
                        'xl' => 1,
                        '2xl' => 1,
                    ])->schema(
                        [
                            Section::make('Content')
                                ->schema(
                                    array_merge([
                                        TextInput::make('name')
                                            ->label('Naam')
                                            ->required()
                                            ->maxLength(100)
                                            ->rules([
                                                'max:100',
                                            ]),
                                        TextInput::make('order')
                                            ->label('Volgorde')
                                            ->numeric()
                                            ->required()
                                            ->default(1)
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
                                            ->columnSpan([
                                                'default' => 1,
                                                'sm' => 1,
                                                'md' => 1,
                                                'lg' => 1,
                                                'xl' => 1,
                                                '2xl' => 1,
                                            ]),
                                    ])
                                ), ]
                    ), ]
            );
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
            'index' => ListProductCharacteristic::route('/'),
            'create' => CreateProductCharacteristic::route('/create'),
            'edit' => EditProductCharacteristic::route('/{record}/edit'),
        ];
    }
}
