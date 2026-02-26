<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources;

use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Filament\Forms\Form;
use Dashed\DashedEcommerceCore\Models\Cart;
use Dashed\DashedEcommerceCore\Filament\Resources\CartResource\Pages;
use Dashed\DashedEcommerceCore\Filament\Resources\CartResource\RelationManagers\ItemsRelationManager;

class CartResource extends Resource
{
    protected static ?string $model = Cart::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-shopping-cart';
    protected static string|\UnitEnum|null $navigationGroup = 'E-commerce';
    protected static ?string $navigationLabel = 'Winkelwagens';
    protected static ?string $modelLabel = 'Winkelwagen';
    protected static ?string $pluralModelLabel = 'Winkelwagens';

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Winkelwagen')
                ->columnSpanFull()
                ->schema([
                    Forms\Components\TextInput::make('id')
                        ->disabled(),
                    Forms\Components\TextInput::make('type')
                        ->label('Type')
                        ->disabled(),
                    Forms\Components\TextInput::make('user_id')
                        ->label('Gebruiker')
                        ->disabled(),
                    Forms\Components\TextInput::make('created_at')
                        ->label('Aangemaakt op')
                        ->disabled(),
                    Forms\Components\TextInput::make('updated_at')
                        ->label('Bijgewerkt op')
                        ->disabled(),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('updated_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('type')
                    ->label('Type')
                    ->sortable()
                    ->searchable()
                    ->formatStateUsing(function ($state) {
                        return match ($state) {
                            'default' => 'Webshop',
                            'pos' => 'POS',
                            'handorder' => 'Handorder',
                            'customer-pos' => 'Customer POS',
                            default => $state,
                        };
                    }),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Gebruiker')
                    ->default('-')
                    ->searchable(),
                Tables\Columns\TextColumn::make('items_count')
                    ->counts('items')
                    ->label('Items')
                    ->sortable(),
                Tables\Columns\TextColumn::make('updated_at')->dateTime()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label('Type')
                    ->options([
                        'default' => 'Webshop',
                        'pos' => 'pos',
                        'handorder' => 'handorder',
                        'customer-pos' => 'customer-pos',
                    ]),
            ])
            ->actions([
                ViewAction::make(),

                Action::make('empty')
                    ->label('Leeggooien')
                    ->icon('heroicon-o-trash')
                    ->requiresConfirmation()
                    ->action(function (Cart $record) {
                        $record->items()->delete();
                    }),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            ItemsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCarts::route('/'),
            'view' => Pages\ViewCart::route('/{record}'),
        ];
    }
}
