<?php

namespace Qubiqx\QcommerceEcommerceCore\Filament\Resources;

use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
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
use Qubiqx\QcommerceEcommerceCore\Filament\Resources\ProductFilterResource\Pages\CreateProductFilter;
use Qubiqx\QcommerceEcommerceCore\Filament\Resources\ProductFilterResource\RelationManagers\ProductFilterOptionRelationManager;

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
                Grid::make([
                    'default' => 1,
                    'sm' => 1,
                    'md' => 1,
                    'lg' => 1,
                    'xl' => 1,
                    '2xl' => 1,
                ])->schema([
                    Section::make('Content')
                        ->schema(array_merge([
                            Toggle::make('hide_filter_on_overview_page')
                                ->label('Moet deze filter verborgen worden op de overzichts pagina van de producten?'),
                            TextInput::make('name')
                                ->label('Naam')
                                ->required()
                                ->maxLength(100)
                                ->rules([
                                    'max:100',
                                ])
                                ->columnSpan([
                                    'default' => 1,
                                    'sm' => 1,
                                    'md' => 1,
                                    'lg' => 1,
                                    'xl' => 1,
                                    '2xl' => 1,
                                ]),
                        ]))
                ])
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
                    ->getStateUsing(fn($record) => $record->productFilterOptions->count()),
            ])
            ->filters([
                //
            ]);
    }

    public static function getRelations(): array
    {
        return [
            ProductFilterOptionRelationManager::class,
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
