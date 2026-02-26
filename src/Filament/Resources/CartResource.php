<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources;

use Filament\Forms;
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

    protected static ?string $navigationIcon = 'heroicon-o-shopping-cart';
    protected static ?string $navigationGroup = 'E-commerce';
    protected static ?string $navigationLabel = 'Winkelwagens';
    protected static ?string $modelLabel = 'Winkelwagen';
    protected static ?string $pluralModelLabel = 'Winkelwagens';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Winkelwagen')
                ->schema([
                    Forms\Components\TextInput::make('id')->disabled(),
                    Forms\Components\TextInput::make('cart_type')->label('Cart type')->disabled(),
                    Forms\Components\TextInput::make('status')->disabled(),
                    Forms\Components\TextInput::make('user_id')->label('User ID')->disabled(),
                    Forms\Components\TextInput::make('created_at')->disabled(),
                    Forms\Components\TextInput::make('updated_at')->disabled(),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('updated_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('id')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('cart_type')->label('Type')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('status')->badge()->sortable(),
                Tables\Columns\TextColumn::make('user.name')->label('User')->searchable(),
                Tables\Columns\TextColumn::make('items_count')
                    ->counts('items')
                    ->label('Items')
                    ->sortable(),
                Tables\Columns\TextColumn::make('updated_at')->dateTime()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'active' => 'Active',
                        'closed' => 'Closed',
                        'converted' => 'Converted',
                    ]),
                Tables\Filters\SelectFilter::make('cart_type')
                    ->label('Type')
                    ->options([
                        'default' => 'default',
                        'pos' => 'pos',
                        'handorder' => 'handorder',
                        'customer-pos' => 'customer-pos',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),

                Tables\Actions\Action::make('empty')
                    ->label('Leeggooien')
                    ->icon('heroicon-o-trash')
                    ->requiresConfirmation()
                    ->action(function (Cart $record) {
                        $record->items()->delete();
                    }),

                Tables\Actions\Action::make('close')
                    ->label('Sluiten')
                    ->icon('heroicon-o-lock-closed')
                    ->requiresConfirmation()
                    ->action(function (Cart $record) {
                        $record->status = 'closed';
                        $record->save();
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
            'view'  => Pages\ItemsRelationManager::route('/{record}'),
        ];
    }
}
