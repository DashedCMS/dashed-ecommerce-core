<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources\OnAccountCustomerResource\RelationManagers;

use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Dashed\DashedEcommerceCore\Models\Order;
use Filament\Tables\Columns\TextColumn;
use Filament\Resources\RelationManagers\RelationManager;
use Dashed\DashedEcommerceCore\Filament\Resources\OrderResource;

/**
 * Openstaande orders op rekening van deze klant. `User::orders()` bestaat
 * niet, dus dit is geen echte Eloquent-relatie maar een `->query()` op de
 * tabel via `Order::scopeOnAccountOpen()`. `$relationship` is puur de naam
 * voor de tabtitel (gebruikt door `getRelationshipTitle()` als er geen
 * `$relatedResource` staat); die methode wordt nooit als relatie
 * aangeroepen omdat `->query()` in `table()` voorrang krijgt boven de
 * relatie-gebaseerde query.
 *
 * `canViewForRecord()` is bewust overschreven naar `OrderResource::canViewAny()`
 * (dus de `view_order`-permissie), in plaats van `$shouldSkipAuthorization`
 * of `$relatedResource`. `$shouldSkipAuthorization = true` liet ELKE
 * ingelogde beheerder de orders zien, ook zonder `view_order` (bijvoorbeeld
 * iemand met alleen `edit_user`). `$relatedResource = OrderResource::class`
 * zou dat wel goed afdwingen, maar Filaments basis `makeTable()` roept dan
 * ook `OrderResource::configureTable($table)` aan vóór onze eigen `table()`,
 * wat de volledige orderlijst-configuratie (filters, bulk-acties, kolommen)
 * van `OrderResource::table()` op deze kleine tabel zou plakken voordat wij
 * hem overschrijven. Rechtstreeks `canViewForRecord()` overschrijven
 * vermijdt dat en raakt `$ownerRecord->orders()` nooit aan.
 */
class OpenOrdersRelationManager extends RelationManager
{
    protected static string $relationship = 'orders';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return OrderResource::canViewAny();
    }

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
