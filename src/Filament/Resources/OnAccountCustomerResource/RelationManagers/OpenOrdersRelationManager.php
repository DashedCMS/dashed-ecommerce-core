<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources\OnAccountCustomerResource\RelationManagers;

use Filament\Tables\Table;
use Dashed\DashedEcommerceCore\Models\Order;
use Filament\Tables\Columns\TextColumn;
use Filament\Resources\RelationManagers\RelationManager;

/**
 * Openstaande orders op rekening van deze klant. `User::orders()` bestaat
 * niet, dus dit is geen echte Eloquent-relatie maar een `->query()` op de
 * tabel via `Order::scopeOnAccountOpen()`. `$relationship` is puur de naam
 * voor de tabtitel; zonder een echte `orders()`-relatie zou Filaments
 * ingebouwde autorisatiecheck er wel op proberen aan te roepen, vandaar de
 * uitgezette `$shouldSkipAuthorization`, net als de rest van dit scherm
 * geen aparte rechten kent.
 */
class OpenOrdersRelationManager extends RelationManager
{
    protected static string $relationship = 'orders';

    protected static bool $shouldSkipAuthorization = true;

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => Order::query()->onAccountOpen()->where('user_id', $this->getOwnerRecord()->id)->withCount('paymentReminders'))
            ->columns([
                TextColumn::make('invoice_id')->label(__('Factuurnummer')),
                TextColumn::make('payment_due_at')->label(__('Vervaldatum'))->date('d-m-Y')->sortable()
                    ->color(fn ($record) => $record->payment_due_at?->isPast() ? 'danger' : null),
                TextColumn::make('open_amount')->label(__('Open bedrag'))->money('EUR'),
                TextColumn::make('payment_reminders_count')->label(__('Herinneringen')),
            ])
            ->defaultSort('payment_due_at')
            ->recordUrl(fn (Order $record) => route('filament.dashed.resources.orders.view', [$record]));
    }
}
