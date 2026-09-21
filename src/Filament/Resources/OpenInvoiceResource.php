<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources;

use UnitEnum;
use BackedEnum;
use Filament\Tables\Table;
use Filament\Actions\BulkAction;
use Filament\Resources\Resource;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Dashed\DashedEcommerceCore\Models\Order;
use Illuminate\Database\Eloquent\Collection;
use Dashed\DashedEcommerceCore\Services\OnAccount\OnAccountBalance;
use Dashed\DashedEcommerceCore\Services\OnAccount\PaymentReminderSender;
use Dashed\DashedEcommerceCore\Filament\Resources\OpenInvoiceResource\Pages\ListOpenInvoices;

/**
 * Openstaande facturen op rekening, over alle klanten heen: waar het
 * "Klanten op rekening"-scherm het saldo per klant toont, is dit de
 * werklijst per factuur (vervaldatum, herinneringsstap, pauzeknop) waar een
 * beheerder zijn incasso-ronde langs doet.
 */
class OpenInvoiceResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static ?string $slug = 'open-invoices';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    protected static string|UnitEnum|null $navigationGroup = 'Retouren';

    protected static ?int $navigationSort = 2;

    public static function getNavigationLabel(): string
    {
        return __('Openstaande facturen');
    }

    public static function getModelLabel(): string
    {
        return __('Openstaande factuur');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Openstaande facturen');
    }

    public static function getNavigationBadge(): ?string
    {
        $count = Order::query()->onAccountOpen()->where('payment_due_at', '<', now())->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->select('dashed__orders.*')
            ->selectRaw(OnAccountBalance::outstandingSql().' as open_amount')
            ->onAccountOpen()
            ->withCount('paymentReminders');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('invoice_id')
                    ->label(__('Factuurnummer'))
                    ->searchable(),
                TextColumn::make('company_name')
                    ->label(__('Bedrijf'))
                    ->searchable(),
                TextColumn::make('email')
                    ->label(__('E-mailadres'))
                    ->searchable(),
                TextColumn::make('payment_due_at')
                    ->label(__('Vervaldatum'))
                    ->date('d-m-Y')
                    ->sortable()
                    ->color(fn ($record) => $record->payment_due_at?->isPast() ? 'danger' : null),
                TextColumn::make('open_amount')
                    ->label(__('Open bedrag'))
                    ->money('EUR'),
                TextColumn::make('payment_reminders_count')
                    ->label(__('Herinneringen')),
                IconColumn::make('payment_reminders_paused_at')
                    ->label(__('Gepauzeerd'))
                    ->boolean(),
            ])
            ->defaultSort('payment_due_at')
            ->filters([
                SelectFilter::make('user_id')
                    ->label(__('Klant'))
                    ->relationship('user', 'email')
                    ->searchable(),
                Filter::make('overdue')
                    ->toggle()
                    ->label(__('Alleen vervallen'))
                    ->query(fn (Builder $query, array $data): Builder => ($data['isActive'] ?? false) ? $query->where('payment_due_at', '<', now()) : $query),
                SelectFilter::make('reminder_stage')
                    ->label(__('Herinneringsstap'))
                    ->options([0 => __('Nog geen'), 1 => '1', 2 => '2', 3 => '3'])
                    ->query(function (Builder $query, array $data): Builder {
                        if (($data['value'] ?? null) === null || $data['value'] === '') {
                            return $query;
                        }

                        $stage = (int) $data['value'];

                        return $stage === 0
                            ? $query->whereDoesntHave('paymentReminders')
                            : $query->whereHas('paymentReminders', fn ($q) => $q->where('stage', $stage));
                    }),
            ])
            ->recordUrl(fn (Order $record) => route('filament.dashed.resources.orders.view', ['record' => $record]))
            ->toolbarActions([
                BulkAction::make('sendPaymentReminder')
                    ->label(__('Herinnering sturen'))
                    ->icon('heroicon-o-bell-alert')
                    ->requiresConfirmation()
                    ->authorize(fn () => auth()->user()?->can('edit_order') ?? false)
                    ->deselectRecordsAfterCompletion()
                    ->action(function (Collection $records) {
                        foreach ($records as $order) {
                            PaymentReminderSender::sendManual($order, auth()->user());
                        }

                        Notification::make()
                            ->title(__('Herinneringen verstuurd'))
                            ->success()
                            ->send();
                    }),
            ]);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOpenInvoices::route('/'),
        ];
    }
}
