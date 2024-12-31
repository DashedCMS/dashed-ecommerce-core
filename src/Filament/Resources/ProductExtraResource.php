<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources;

use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Filament\Resources\Resource;
use Illuminate\Support\Facades\DB;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Repeater;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Forms\Components\Actions\Action;
use Filament\Resources\Concerns\Translatable;
use Filament\Tables\Actions\DeleteBulkAction;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Models\ProductExtra;
use Dashed\DashedCore\Filament\Concerns\HasCustomBlocksTab;
use Dashed\DashedEcommerceCore\Filament\Resources\ProductExtraResource\Pages\EditProductExtra;
use Dashed\DashedEcommerceCore\Filament\Resources\ProductExtraResource\Pages\ListProductExtra;
use Dashed\DashedEcommerceCore\Filament\Resources\ProductExtraResource\Pages\CreateProductExtra;

class ProductExtraResource extends Resource
{
    use Translatable;
    use HasCustomBlocksTab;

    protected static ?string $model = ProductExtra::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $navigationIcon = 'heroicon-o-beaker';
    protected static ?string $navigationGroup = 'Producten';
    protected static ?string $navigationLabel = 'Product extras';
    protected static ?string $label = 'Product extra';
    protected static ?string $pluralLabel = 'Product extras';
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
            ->schema(array_merge([
                TextInput::make('name')
                    ->label('Naam')
                    ->required()
                    ->maxLength(255),
                Select::make('type')
                    ->label('Type')
                    ->options([
                        'single' => '1 optie',
                        'multiple' => 'Meerdere opties',
                        'checkbox' => 'Checkbox',
                        'input' => 'Invulveld',
                        'image' => 'Afbeelding kiezen',
                        'file' => 'Upload bestand',
                    ])
                    ->default('single')
                    ->required()
                    ->reactive(),
                Select::make('input_type')
                    ->label('Input type')
                    ->options([
                        'text' => 'Tekst',
                        'numeric' => 'Getal',
                        'date' => 'Datum',
                        'dateTime' => 'Datum + tijd',
                    ])
                    ->default('text')
                    ->visible(fn (Get $get) => $get('type') == 'input')
                    ->required(fn (Get $get) => $get('type') == 'input'),
                TextInput::make('min_length')
                    ->label('Minimale lengte/waarde')
                    ->numeric()
                    ->visible(fn (Get $get) => $get('type') == 'input')
                    ->required(fn (Get $get) => $get('type') == 'input'),
                TextInput::make('max_length')
                    ->label('Maximale lengte/waarde')
                    ->numeric()
                    ->visible(fn (Get $get) => $get('type') == 'input')
                    ->required(fn (Get $get) => $get('type') == 'input')
                    ->reactive(),
                Toggle::make('required')
                    ->label('Verplicht'),
                Repeater::make('productExtraOptions')
                    ->relationship('productExtraOptions')
                    ->cloneable(fn (Get $get) => $get('type') != 'checkbox')
                    ->label('Opties van deze product extra')
                    ->visible(fn (Get $get) => $get('type') == 'single' || $get('type') == 'multiple' || $get('type') == 'checkbox' || $get('type') == 'imagePicker')
                    ->required(fn (Get $get) => $get('type') == 'single' || $get('type') == 'multiple' || $get('type') == 'checkbox' || $get('type') == 'imagePicker')
                    ->maxItems(fn (Get $get) => $get('type') == 'checkbox' ? 1 : 50)
                    ->reactive()
                    ->schema([
                        TextInput::make('value')
                            ->label('Waarde')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('price')
                            ->required()
                            ->label('Meerprijs van deze optie')
                            ->prefix('€')
                            ->helperText('Voorbeeld: 10.25')
                            ->numeric()
                            ->minValue(0.00)
                            ->maxValue(10000),
                        mediaHelper()->field('image', 'Afbeelding'),
                        Toggle::make('calculate_only_1_quantity')
                            ->label('Deze extra maar 1x meetellen, ook al worden er meerdere van het product gekocht'),
                    ])
                    ->columnSpan(2),
                Select::make('products')
                    ->relationship('products', 'name')
                    ->label('Gekoppelde producten')
                    ->getSearchResultsUsing(fn (string $search) => Product::where(DB::raw('lower(name)'), 'like', '%' . strtolower($search) . '%')->limit(50)->pluck('name', 'id'))
                    ->searchable()
                    ->multiple()
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->nameWithParents)
                    ->hintAction(
                        Action::make('addAllProducts')
                        ->label('Voeg alle producten toe')
                        ->action(function (Set $set) {
                            $set('products', Product::all()->pluck('id')->toArray());
                        }),
                    ),
            ], static::customBlocksTab('productExtraOptionBlocks')));
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Naam')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('products_count')
                    ->counts('products')
                    ->label('Aantal producten')
                    ->sortable(),
            ])
            ->reorderable('order')
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
            ])
            ->query(ProductExtra::query()->where('global', 1));
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProductExtra::route('/'),
            'create' => CreateProductExtra::route('/create'),
            'edit' => EditProductExtra::route('/{record}/edit'),
        ];
    }
}
