<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources;

use Filament\Forms\Form;
use Filament\Tables\Table;
use Filament\Resources\Resource;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Section;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Resources\Concerns\Translatable;
use Filament\Tables\Actions\DeleteBulkAction;
use Dashed\DashedCore\Classes\QueryHelpers\SearchQuery;
use Dashed\DashedEcommerceCore\Models\ProductCharacteristics;
use Dashed\DashedEcommerceCore\Filament\Resources\ProductCharacteristicResource\Pages\EditProductCharacteristic;
use Dashed\DashedEcommerceCore\Filament\Resources\ProductCharacteristicResource\Pages\ListProductCharacteristic;
use Dashed\DashedEcommerceCore\Filament\Resources\ProductCharacteristicResource\Pages\CreateProductCharacteristic;

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
                    Section::make('Content')
                        ->schema(
                            array_merge([
                                TextInput::make('name')
                                    ->label('Naam')
                                    ->required()
                                    ->maxLength(100),
                                TextInput::make('order')
                                    ->label('Volgorde')
                                    ->numeric()
                                    ->required()
                                    ->default(1)
                                    ->minLength(1)
                                    ->maxLength(100),
                                Toggle::make('hide_from_public')
                                    ->label('Dit kenmerk verbergen op de website'),
                            ])
                        )
                        ->columns(2),
                ]
            );
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Naam')
                    ->searchable(query: SearchQuery::make())
                    ->sortable(),
                TextColumn::make('order')
                    ->label('Volgorde')
                    ->sortable(),
                IconColumn::make('hide_from_public')
                    ->label('Tonen op website')
                    ->trueIcon('heroicon-o-eye-slash')
                    ->trueColor('danger')
                    ->falseIcon('heroicon-o-eye')
                    ->falseColor('success')
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                EditAction::make()
                    ->button(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
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
