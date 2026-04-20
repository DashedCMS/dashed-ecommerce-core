<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources;

use UnitEnum;
use BackedEnum;
use Filament\Tables\Table;
use Filament\Actions\Action;
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
use Filament\Notifications\Notification;
use Dashed\DashedEcommerceCore\Models\AbandonedCartFlow;
use Dashed\DashedEcommerceCore\Filament\Resources\AbandonedCartFlowResource\Pages\EditAbandonedCartFlow;
use Dashed\DashedEcommerceCore\Filament\Resources\AbandonedCartFlowResource\Pages\ListAbandonedCartFlows;
use Dashed\DashedEcommerceCore\Filament\Resources\AbandonedCartFlowResource\Pages\CreateAbandonedCartFlow;

class AbandonedCartFlowResource extends Resource
{
    protected static ?string $model = AbandonedCartFlow::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-envelope';
    protected static string | UnitEnum | null $navigationGroup = 'E-commerce';
    protected static ?string $navigationLabel = 'Verlaten winkelwagen';
    protected static ?string $label = 'Email flow';
    protected static ?string $pluralLabel = 'Email flows';
    protected static ?int $navigationSort = 55;

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            TextInput::make('name')
                ->label('Naam')
                ->required()
                ->maxLength(255),

            TextInput::make('discount_prefix')
                ->label('Kortingscode prefix')
                ->helperText('Prefix voor gegenereerde kortingscodes, bijv. TERUG geeft TERUG-ABCD1234')
                ->default('TERUG')
                ->maxLength(20),

            Toggle::make('is_active')
                ->label('Actieve flow')
                ->helperText('Slechts één flow kan actief zijn tegelijk.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Naam')
                    ->searchable()
                    ->weight('bold'),
                TextColumn::make('steps_count')
                    ->label('Stappen')
                    ->counts('steps')
                    ->badge()
                    ->color('info'),
                TextColumn::make('pending_count')
                    ->label('In wacht')
                    ->state(fn ($record) => $record->emails()->whereNull('sent_at')->whereNull('cancelled_at')->count())
                    ->badge()
                    ->color('warning'),
                TextColumn::make('sent_count')
                    ->label('Verzonden')
                    ->state(fn ($record) => $record->emails()->whereNotNull('sent_at')->count())
                    ->badge()
                    ->color('info'),
                TextColumn::make('converted_count')
                    ->label('Geconverteerd')
                    ->state(fn ($record) => $record->emails()->whereNotNull('converted_at')->count())
                    ->badge()
                    ->color('success'),
                IconColumn::make('is_active')
                    ->label('Actief')
                    ->boolean(),
                TextColumn::make('updated_at')
                    ->label('Bijgewerkt')
                    ->dateTime('d-m-Y H:i', 'Europe/Amsterdam')
                    ->sortable(),
            ])
            ->recordActions([
                Action::make('activate')
                    ->label('Activeren')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn ($record) => ! $record->is_active)
                    ->action(function ($record) {
                        $record->activate();
                        Notification::make()->title('Flow geactiveerd')->success()->send();
                    }),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([DeleteBulkAction::make()]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            AbandonedCartFlowResource\RelationManagers\FlowStepsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAbandonedCartFlows::route('/'),
            'create' => CreateAbandonedCartFlow::route('/create'),
            'edit' => EditAbandonedCartFlow::route('/{record}/edit'),
        ];
    }
}
