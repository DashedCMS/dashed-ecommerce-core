<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources\OnAccountCustomerResource\Widgets;

use Dashed\DashedCore\Models\User;
use Illuminate\Database\Eloquent\Model;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Dashed\DashedEcommerceCore\Classes\CurrencyHelper;
use Dashed\DashedEcommerceCore\Services\OnAccount\OnAccountBalance;
use Dashed\DashedEcommerceCore\Services\OnAccount\OnAccountSettings;

/**
 * Vier kaarten boven een klant op rekening: openstaand, vervallen, ruimte
 * binnen de limiet en de status die `OnAccount::check()` ook zou geven.
 */
class OnAccountCustomerStats extends StatsOverviewWidget
{
    public ?Model $record = null;

    protected function getStats(): array
    {
        /** @var User $user */
        $user = $this->record;

        $open = OnAccountBalance::open($user);
        $overdue = OnAccountBalance::overdue($user);
        $limit = OnAccountSettings::creditLimitFor($user);

        return [
            Stat::make(__('Openstaand'), CurrencyHelper::formatPrice($open)),
            Stat::make(__('Vervallen'), CurrencyHelper::formatPrice($overdue))
                ->color($overdue > 0 ? 'danger' : null),
            Stat::make(__('Ruimte binnen limiet'), $limit === null ? __('Geen limiet') : CurrencyHelper::formatPrice($limit - $open)),
            Stat::make(__('Status'), $this->statusLabel($user))
                ->color($this->statusColor($user)),
        ];
    }

    protected function statusLabel(User $user): string
    {
        if ($user->on_account_blocked_at) {
            return __('Handmatig geblokkeerd');
        }

        $blockAfter = OnAccountSettings::blockAfterDays();
        if ($blockAfter > 0 && OnAccountBalance::hasOverdue($user, $blockAfter)) {
            return __('Geblokkeerd: vervallen facturen');
        }

        return __('Actief');
    }

    protected function statusColor(User $user): ?string
    {
        if ($user->on_account_blocked_at) {
            return 'danger';
        }

        $blockAfter = OnAccountSettings::blockAfterDays();
        if ($blockAfter > 0 && OnAccountBalance::hasOverdue($user, $blockAfter)) {
            return 'danger';
        }

        return 'success';
    }
}
