<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources\OnAccountCustomerResource\Pages;

use Illuminate\Support\Facades\DB;
use Filament\Resources\Pages\EditRecord;
use Dashed\DashedEcommerceCore\Filament\Resources\OnAccountCustomerResource;
use Dashed\DashedEcommerceCore\Filament\Resources\OnAccountCustomerResource\Widgets\OnAccountCustomerStats;

class EditOnAccountCustomer extends EditRecord
{
    protected static string $resource = OnAccountCustomerResource::class;

    protected function getHeaderWidgets(): array
    {
        return [OnAccountCustomerStats::class];
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    /**
     * De twee vinklijst-/toggle-velden zijn `dehydrated(false)` en worden
     * daarom hier zelf verwerkt: de blokkade als stempel op de klant, de
     * betaalmethodes op rekening als pivotrijen. Alleen de methodes op
     * rekening worden aangeraakt, een koppeling aan een gewone methode
     * blijft staan.
     */
    protected function afterSave(): void
    {
        $state = $this->form->getRawState();
        $record = $this->record;

        $record->forceFill([
            'on_account_blocked_at' => ($state['on_account_blocked'] ?? false)
                ? ($record->on_account_blocked_at ?? now())
                : null,
        ])->save();

        $onAccountIds = OnAccountCustomerResource::onAccountMethodsQuery()->pluck('id');
        DB::table('dashed__payment_method_users')->where('user_id', $record->id)->whereIn('payment_method_id', $onAccountIds)->delete();
        foreach (array_intersect($state['payment_methods'] ?? [], $onAccountIds->all()) as $methodId) {
            DB::table('dashed__payment_method_users')->insert(['payment_method_id' => $methodId, 'user_id' => $record->id]);
        }
    }
}
