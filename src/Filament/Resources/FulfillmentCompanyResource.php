<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources;

use UnitEnum;
use BackedEnum;
use Filament\Tables\Table;
use Filament\Schemas\Schema;
use Filament\Actions\EditAction;
use Filament\Resources\Resource;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Forms\Components\Toggle;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Dashed\DashedEcommerceCore\Models\FulfillmentCompany;
use LaraZeus\SpatieTranslatable\Resources\Concerns\Translatable;
use Dashed\DashedEcommerceCore\Filament\Resources\FulfillmentCompanyResource\Pages\EditFulfillmentCompany;
use Dashed\DashedEcommerceCore\Filament\Resources\FulfillmentCompanyResource\Pages\ListFulfillmentCompany;
use Dashed\DashedEcommerceCore\Filament\Resources\FulfillmentCompanyResource\Pages\CreateFulfillmentCompany;

class FulfillmentCompanyResource extends Resource
{
    //    use Translatable;

    protected static ?string $model = FulfillmentCompany::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-truck';
    protected static string | UnitEnum | null $navigationGroup = 'E-commerce';
    protected static ?string $navigationLabel = 'Fulfillment bedrijven';
    protected static ?string $label = 'Fulfillment bedrijf';
    protected static ?string $pluralLabel = 'Fulfillment bedrijven';
    protected static ?int $navigationSort = 1000;

    public static function getGloballySearchableAttributes(): array
    {
        return [
            'name',
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                TextInput::make('name')
                    ->label('Naam')
                    ->maxLength(255)
                    ->required(),
                TextInput::make('email')
                    ->label('Email')
                    ->maxLength(255)
                    ->email()
                    ->required(),
                Toggle::make('process_automatically')
                    ->label('Automatisch verwerken')
                    ->helperText('Als je dit aan zet worden bestelling met producten van dit fulfillment bedrijf automatisch naar het bedrijf gemaild.'),
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
                TextColumn::make('email')
                    ->label('Email')
                    ->url(fn ($record) => "mailto:{$record->email}")
                    ->searchable()
                    ->sortable(),
                IconColumn::make('process_automatically')
                    ->label('Automatisch verwerken')
                    ->trueIcon('heroicon-s-check-circle')
                    ->falseIcon('heroicon-s-x-circle')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('products_count')
                    ->label('Aantal producten')
                    ->counts('products')
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
            ->toolbarActions([
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
            'index' => ListFulfillmentCompany::route('/'),
            'create' => CreateFulfillmentCompany::route('/create'),
            'edit' => EditFulfillmentCompany::route('/{record}/edit'),
        ];
    }
}
