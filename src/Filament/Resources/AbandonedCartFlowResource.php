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
use Filament\Forms\Components\CheckboxList;
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

            CheckboxList::make('triggers')
                ->label('Triggers')
                ->options([
                    'cart_with_email' => 'Verlaten winkelwagen (met email)',
                    'cancelled_order' => 'Geannuleerde bestelling (niet betaald)',
                ])
                ->descriptions([
                    'cart_with_email' => 'Start flow wanneer een cart een emailadres krijgt en niet wordt afgerond.',
                    'cancelled_order' => 'Start flow wanneer een bestelling wordt geannuleerd zonder dat er ooit betaald is.',
                ])
                ->default(['cart_with_email'])
                ->minItems(1)
                ->required(),
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
                TextColumn::make('triggers')
                    ->label('Triggers')
                    ->badge()
                    ->separator(',')
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'cart_with_email' => 'Cart',
                        'cancelled_order' => 'Geannuleerde order',
                        default => (string) $state,
                    })
                    ->color(fn ($state) => match ($state) {
                        'cart_with_email' => 'success',
                        'cancelled_order' => 'info',
                        default => 'gray',
                    }),
                TextColumn::make('pending_count')
                    ->label('In wacht (cart / order)')
                    ->state(function ($record) {
                        $cart = $record->emails()
                            ->where('dashed__abandoned_cart_emails.trigger_type', 'cart_with_email')
                            ->whereNull('sent_at')->whereNull('cancelled_at')->count();
                        $order = $record->emails()
                            ->where('dashed__abandoned_cart_emails.trigger_type', 'cancelled_order')
                            ->whereNull('sent_at')->whereNull('cancelled_at')->count();
                        return "{$cart} / {$order}";
                    })
                    ->badge()
                    ->color('warning'),
                TextColumn::make('sent_count')
                    ->label('Verzonden (cart / order)')
                    ->state(function ($record) {
                        $cart = $record->emails()
                            ->where('dashed__abandoned_cart_emails.trigger_type', 'cart_with_email')
                            ->whereNotNull('sent_at')->count();
                        $order = $record->emails()
                            ->where('dashed__abandoned_cart_emails.trigger_type', 'cancelled_order')
                            ->whereNotNull('sent_at')->count();
                        return "{$cart} / {$order}";
                    })
                    ->badge()
                    ->color('info'),
                TextColumn::make('converted_count')
                    ->label('Geconverteerd (cart / order)')
                    ->state(function ($record) {
                        $cart = $record->emails()
                            ->where('dashed__abandoned_cart_emails.trigger_type', 'cart_with_email')
                            ->whereNotNull('converted_at')->count();
                        $order = $record->emails()
                            ->where('dashed__abandoned_cart_emails.trigger_type', 'cancelled_order')
                            ->whereNotNull('converted_at')->count();
                        return "{$cart} / {$order}";
                    })
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
