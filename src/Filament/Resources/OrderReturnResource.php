<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources;

use UnitEnum;
use BackedEnum;
use Filament\Tables\Table;
use Filament\Schemas\Schema;
use Filament\Actions\ViewAction;
use Filament\Resources\Resource;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Schemas\Components\Fieldset;
use Filament\Tables\Filters\SelectFilter;
use Filament\Infolists\Components\TextEntry;
use Dashed\DashedEcommerceCore\Models\OrderReturn;
use Filament\Infolists\Components\RepeatableEntry;
use Dashed\DashedEcommerceCore\Classes\CurrencyHelper;
use Dashed\DashedEcommerceCore\Filament\Resources\OrderReturnResource\Actions\ReturnActions;

class OrderReturnResource extends Resource
{
    protected static ?string $model = OrderReturn::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-arrow-uturn-left';

    protected static string|UnitEnum|null $navigationGroup = 'Retouren';

    protected static ?string $navigationLabel = 'Retouren';

    protected static ?string $label = 'Retouraanvraag';

    protected static ?string $pluralLabel = 'Retouraanvragen';

    protected static ?int $navigationSort = 1;

    public static function getNavigationBadge(): ?string
    {
        $count = OrderReturn::notHandled()->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['order', 'creditOrder', 'lines.orderProduct', 'lines.returnReason']))
            ->defaultSort('requested_at', 'desc')
            ->columns([
                TextColumn::make('order.invoice_id')
                    ->label(__('Bestelling'))
                    ->formatStateUsing(fn ($state, $record) => $state ?: ('#' . $record->order_id))
                    ->url(fn ($record) => $record->order_id ? OrderResource::getUrl('edit', ['record' => $record->order_id]) : null),
                TextColumn::make('email')
                    ->label(__('E-mail'))
                    ->searchable(),
                TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge()
                    ->formatStateUsing(fn ($state) => OrderReturn::statusLabels()[$state] ?? $state)
                    ->color(fn ($state) => match ($state) {
                        OrderReturn::STATUS_REQUESTED => 'warning',
                        OrderReturn::STATUS_APPROVED => 'success',
                        OrderReturn::STATUS_REJECTED => 'danger',
                        OrderReturn::STATUS_HANDLED => 'gray',
                        OrderReturn::STATUS_CLOSED => 'gray',
                        default => 'gray',
                    }),
                IconColumn::make('auto_accepted')
                    ->label(__('Automatisch'))
                    ->boolean(),
                TextColumn::make('requested_at')
                    ->label(__('Aangevraagd op'))
                    ->dateTime('d-m-Y H:i')
                    ->sortable(),
                TextColumn::make('lines_count')
                    ->label(__('Regels'))
                    ->counts('lines'),
                TextColumn::make('creditOrder.invoice_id')
                    ->label(__('Creditorder'))
                    ->placeholder('-')
                    ->url(fn ($record) => $record->credit_order_id ? OrderResource::getUrl('view', ['record' => $record->credit_order_id]) : null),
                TextColumn::make('credited')
                    ->label(__('Gecrediteerd'))
                    ->getStateUsing(fn ($record) => $record->credit_order_id ? CurrencyHelper::formatPrice($record->creditedAmount()) : '-'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('Status'))
                    ->options(OrderReturn::statusLabels()),
            ])
            ->recordActions([
                ViewAction::make(),
                ...ReturnActions::all(),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Fieldset::make(__('Retouraanvraag'))
                ->columnSpanFull()
                ->schema([
                    TextEntry::make('order.invoice_id')
                        ->label(__('Bestelling'))
                        ->formatStateUsing(fn ($state, $record) => $state ?: ('#' . $record->order_id)),
                    TextEntry::make('email')
                        ->label(__('E-mail')),
                    TextEntry::make('status')
                        ->label(__('Status'))
                        ->badge()
                        ->formatStateUsing(fn ($state) => OrderReturn::statusLabels()[$state] ?? $state),
                    TextEntry::make('requested_at')
                        ->label(__('Aangevraagd op'))
                        ->dateTime('d-m-Y H:i'),
                    TextEntry::make('auto_accepted')
                        ->label(__('Automatisch goedgekeurd'))
                        ->formatStateUsing(fn ($state) => $state ? 'Ja' : 'Nee'),
                    TextEntry::make('return_label_provider')
                        ->label(__('Retourlabel via'))
                        ->placeholder('-'),
                ]),
            RepeatableEntry::make('lines')
                ->label(__('Geretourneerde producten'))
                ->columnSpanFull()
                ->schema([
                    TextEntry::make('orderProduct.name')
                        ->label(__('Product')),
                    TextEntry::make('quantity')
                        ->label(__('Aantal')),
                    TextEntry::make('processed_quantity')
                        ->label(__('Verwerkt'))
                        ->formatStateUsing(fn ($state, $record) => in_array($record->orderReturn?->status, [OrderReturn::STATUS_HANDLED, OrderReturn::STATUS_CLOSED], true) ? (string) (int) $state : '-'),
                    TextEntry::make('returnReason.label')
                        ->label(__('Reden'))
                        ->formatStateUsing(fn ($state) => is_array($state) ? ($state[app()->getLocale()] ?? reset($state)) : $state),
                    TextEntry::make('reason_note')
                        ->label(__('Toelichting'))
                        ->default('-'),
                ]),
            Fieldset::make(__('Creditorder en terugbetaling'))
                ->columnSpanFull()
                ->visible(fn ($record) => $record->status === OrderReturn::STATUS_HANDLED || $record->status === OrderReturn::STATUS_CLOSED)
                ->schema([
                    TextEntry::make('creditOrder.invoice_id')
                        ->label(__('Creditorder'))
                        ->placeholder(__('Geen creditorder'))
                        ->url(fn ($record) => $record->credit_order_id ? OrderResource::getUrl('view', ['record' => $record->credit_order_id]) : null),
                    TextEntry::make('credited_amount')
                        ->label(__('Gecrediteerd'))
                        ->getStateUsing(fn ($record) => $record->credit_order_id ? CurrencyHelper::formatPrice($record->creditedAmount()) : '-'),
                    TextEntry::make('refund_state')
                        ->label(__('Terugbetaling'))
                        ->getStateUsing(function ($record) {
                            $payment = $record->refundPayment();
                            if (! $payment) {
                                return $record->credit_order_id ? __('Nog niet terugbetaald') : '-';
                            }

                            return __(':bedrag via :methode op :datum', [
                                'bedrag' => CurrencyHelper::formatPrice(abs((float) $payment->amount)),
                                'methode' => $payment->payment_method,
                                'datum' => $payment->created_at?->format('d-m-Y H:i'),
                            ]);
                        }),
                    TextEntry::make('closed_reason')
                        ->label(__('Reden van sluiten'))
                        ->visible(fn ($record) => $record->status === OrderReturn::STATUS_CLOSED),
                ]),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => OrderReturnResource\Pages\ListOrderReturns::route('/'),
            'view' => OrderReturnResource\Pages\ViewOrderReturn::route('/{record}'),
        ];
    }
}
