<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources;

use UnitEnum;
use BackedEnum;
use Filament\Tables\Table;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\TextColumn;
use Filament\Notifications\Notification;
use Filament\Tables\Filters\SelectFilter;
use Dashed\DashedEcommerceCore\Models\OrderReturn;

class OrderReturnResource extends Resource
{
    protected static ?string $model = OrderReturn::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-arrow-uturn-left';

    protected static string|UnitEnum|null $navigationGroup = 'E-commerce';

    protected static ?string $navigationLabel = 'Retouren';

    protected static ?string $label = 'Retouraanvraag';

    protected static ?string $pluralLabel = 'Retouraanvragen';

    protected static ?int $navigationSort = 1;

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('order'))
            ->defaultSort('requested_at', 'desc')
            ->columns([
                TextColumn::make('order.invoice_id')
                    ->label('Bestelling')
                    ->formatStateUsing(fn ($state, $record) => $state ?: ('#' . $record->order_id))
                    ->url(fn ($record) => $record->order_id ? \Dashed\DashedEcommerceCore\Filament\Resources\OrderResource::getUrl('edit', ['record' => $record->order_id]) : null),
                TextColumn::make('email')
                    ->label('E-mail')
                    ->searchable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn ($state) => OrderReturn::statusLabels()[$state] ?? $state)
                    ->color(fn ($state) => match ($state) {
                        OrderReturn::STATUS_REQUESTED => 'warning',
                        OrderReturn::STATUS_APPROVED => 'success',
                        OrderReturn::STATUS_REJECTED => 'danger',
                        OrderReturn::STATUS_HANDLED => 'gray',
                        default => 'gray',
                    }),
                TextColumn::make('requested_at')
                    ->label('Aangevraagd op')
                    ->dateTime('d-m-Y H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(OrderReturn::statusLabels()),
            ])
            ->recordActions([
                Action::make('approve')
                    ->label('Goedkeuren')
                    ->color('success')
                    ->visible(fn ($record) => $record->status === OrderReturn::STATUS_REQUESTED)
                    ->schema([
                        Textarea::make('admin_note')
                            ->label('Notitie (optioneel)'),
                    ])
                    ->action(function ($record, $data) {
                        $record->approve($data['admin_note'] ?? null);

                        Notification::make()
                            ->success()
                            ->title('Retouraanvraag goedgekeurd')
                            ->send();
                    }),
                Action::make('reject')
                    ->label('Afkeuren')
                    ->color('danger')
                    ->visible(fn ($record) => $record->status === OrderReturn::STATUS_REQUESTED)
                    ->schema([
                        Textarea::make('rejected_reason')
                            ->label('Reden')
                            ->required(),
                    ])
                    ->action(function ($record, $data) {
                        $record->reject($data['rejected_reason']);

                        Notification::make()
                            ->success()
                            ->title('Retouraanvraag afgekeurd')
                            ->send();
                    }),
                Action::make('markHandled')
                    ->label('Markeer als afgehandeld')
                    ->color('gray')
                    ->visible(fn ($record) => in_array($record->status, [OrderReturn::STATUS_REQUESTED, OrderReturn::STATUS_APPROVED]))
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        $record->markHandled();

                        Notification::make()
                            ->success()
                            ->title('Retouraanvraag gemarkeerd als afgehandeld')
                            ->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => OrderReturnResource\Pages\ListOrderReturns::route('/'),
        ];
    }
}
