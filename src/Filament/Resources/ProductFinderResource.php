<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources;

use UnitEnum;
use BackedEnum;
use Filament\Tables\Table;
use Filament\Schemas\Schema;
use Filament\Actions\EditAction;
use Filament\Resources\Resource;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Dashed\DashedEcommerceCore\Models\ProductFinder;
use Dashed\DashedEcommerceCore\Models\ProductCategory;

class ProductFinderResource extends Resource
{
    protected static ?string $model = ProductFinder::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-sparkles';

    protected static string|UnitEnum|null $navigationGroup = 'Producten';

    protected static ?string $navigationLabel = 'Product finders';

    protected static ?string $label = 'Product finder';

    protected static ?string $pluralLabel = 'Product finders';

    protected static ?int $navigationSort = 5;

    public static function getGloballySearchableAttributes(): array
    {
        return [
            'name',
        ];
    }

    public static function form(Schema $schema): Schema
    {
        $categoryOptions = ProductCategory::query()
            ->get()
            ->mapWithKeys(fn ($c) => [$c->id => is_array($c->name) ? ($c->name[app()->getLocale()] ?? reset($c->name) ?: (string) $c->id) : $c->name])
            ->toArray();

        return $schema->schema([
            Section::make('Instellingen')
                ->columnSpanFull()
                ->schema([
                    TextInput::make('name')
                        ->label('Naam')
                        ->required()
                        ->maxLength(255),
                    Toggle::make('is_active')
                        ->label('Actief')
                        ->default(true),
                    Textarea::make('intro')
                        ->label('Introductie')
                        ->columnSpanFull(),
                    TextInput::make('result_count')
                        ->label('Aantal resultaten')
                        ->numeric()
                        ->default(4)
                        ->minValue(1),
                    Toggle::make('only_in_stock')
                        ->label('Alleen producten op voorraad')
                        ->default(true),
                    Select::make('category_ids')
                        ->label('Categorieen')
                        ->multiple()
                        ->options($categoryOptions)
                        ->searchable(),
                ])
                ->columns(1),
            Section::make('Vragen')
                ->columnSpanFull()
                ->schema([
                    Repeater::make('questions')
                        ->label('Vragen')
                        ->schema([
                            TextInput::make('label')
                                ->label('Vraag')
                                ->required(),
                            Repeater::make('options')
                                ->label('Antwoordopties')
                                ->schema([
                                    TextInput::make('label')
                                        ->label('Antwoord')
                                        ->required(),
                                ])
                                ->columns(1)
                                ->defaultItems(1)
                                ->collapsible(),
                        ])
                        ->columns(1)
                        ->defaultItems(1)
                        ->collapsible(),
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
                IconColumn::make('is_active')
                    ->label('Actief')
                    ->boolean(),
                TextColumn::make('result_count')
                    ->label('Aantal resultaten')
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make()
                    ->button(),
                DeleteAction::make()
                    ->button(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ProductFinderResource\Pages\ListProductFinders::route('/'),
            'create' => ProductFinderResource\Pages\CreateProductFinder::route('/create'),
            'edit' => ProductFinderResource\Pages\EditProductFinder::route('/{record}/edit'),
        ];
    }
}
