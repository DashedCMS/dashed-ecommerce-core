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
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Dashed\DashedEcommerceCore\Models\ReturnReason;
use LaraZeus\SpatieTranslatable\Resources\Concerns\Translatable;
use Dashed\DashedCore\Classes\Actions\ActionGroups\ToolbarActions;
use Dashed\DashedEcommerceCore\Filament\Resources\ReturnReasonResource\Pages\EditReturnReason;
use Dashed\DashedEcommerceCore\Filament\Resources\ReturnReasonResource\Pages\ListReturnReasons;
use Dashed\DashedEcommerceCore\Filament\Resources\ReturnReasonResource\Pages\CreateReturnReason;

class ReturnReasonResource extends Resource
{
    use Translatable;

    protected static ?string $model = ReturnReason::class;

    protected static ?string $recordTitleAttribute = 'label';

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-arrow-uturn-left';
    protected static UnitEnum|string|null $navigationGroup = 'E-commerce';
    protected static ?string $navigationLabel = 'Retourredenen';
    protected static ?string $label = 'Retourreden';
    protected static ?string $pluralLabel = 'Retourredenen';
    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                TextInput::make('label')
                    ->label('Label')
                    ->required(),
                TextInput::make('sort_order')
                    ->label('Volgorde')
                    ->numeric()
                    ->default(0),
                Toggle::make('is_active')
                    ->label('Actief')
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('label')
                    ->label('Label'),
                TextColumn::make('sort_order')
                    ->label('Volgorde')
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label('Actief')
                    ->boolean(),
            ])
            ->defaultSort('sort_order')
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
            'index' => ListReturnReasons::route('/'),
            'create' => CreateReturnReason::route('/create'),
            'edit' => EditReturnReason::route('/{record}/edit'),
        ];
    }
}
