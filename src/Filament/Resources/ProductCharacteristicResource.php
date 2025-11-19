<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources;

use UnitEnum;
use BackedEnum;
use Filament\Tables\Table;
use Filament\Schemas\Schema;
use Filament\Actions\EditAction;
use Filament\Resources\Resource;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Dashed\DashedCore\Classes\QueryHelpers\SearchQuery;
use Dashed\DashedEcommerceCore\Models\ProductCharacteristics;
use LaraZeus\SpatieTranslatable\Resources\Concerns\Translatable;
use Dashed\DashedCore\Classes\Actions\ActionGroups\ToolbarActions;
use Dashed\DashedEcommerceCore\Filament\Resources\ProductCharacteristicResource\Pages\EditProductCharacteristic;
use Dashed\DashedEcommerceCore\Filament\Resources\ProductCharacteristicResource\Pages\ListProductCharacteristic;
use Dashed\DashedEcommerceCore\Filament\Resources\ProductCharacteristicResource\Pages\CreateProductCharacteristic;

class ProductCharacteristicResource extends Resource
{
    use Translatable;

    protected static ?string $model = ProductCharacteristics::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-shopping-bag';
    protected static string | UnitEnum | null $navigationGroup = 'Producten';
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

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema(
                [
                    Section::make('Content')
                        ->columnSpanFull()
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
                                Textarea::make('notes')
                                    ->label('Notitie')
                                    ->rows(3)
                                    ->columnSpanFull()
                                    ->helperText('Intern gebruik, niet zichtbaar voor klanten'),
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
            ->recordActions([
                EditAction::make()
                    ->button(),
                DeleteAction::make(),
            ])
            ->toolbarActions(ToolbarActions::getActions());
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
