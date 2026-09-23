<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Commands;

use Illuminate\Console\Command;
use Dashed\DashedCore\Models\User;
use Illuminate\Support\Facades\Notification;
use Dashed\DashedEcommerceCore\Models\Quote;
use Filament\Notifications\Notification as FilamentNotification;

class ExpireQuotesCommand extends Command
{
    protected $signature = 'dashed:expire-quotes';

    protected $description = 'Zet verstuurde offertes die hun geldigheidsdatum voorbij zijn op verlopen';

    public function handle(): int
    {
        $expired = Quote::query()
            ->where('status', Quote::STATUS_SENT)
            ->whereNotNull('valid_until')
            ->whereDate('valid_until', '<', today())
            ->get();

        foreach ($expired as $quote) {
            $quote->status = Quote::STATUS_EXPIRED;
            $quote->save();
        }

        if ($expired->isNotEmpty()) {
            // Een verzamelmelding, geen mail per offerte: bij een opruimronde
            // na een stilgevallen scheduler zouden dat er tientallen zijn.
            $admins = User::query()->whereIn('role', ['admin', 'superadmin'])->get();

            foreach ($admins as $admin) {
                FilamentNotification::make()
                    ->title(__('Offertes verlopen'))
                    ->body(__(':aantal offertes zijn verlopen', ['aantal' => $expired->count()]))
                    ->warning()
                    ->sendToDatabase($admin);
            }
        }

        $this->info($expired->count().' offertes op verlopen gezet');

        return self::SUCCESS;
    }
}
