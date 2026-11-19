<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Services\ProductReviews;

use Filament\Actions\Action;
use Dashed\DashedCore\Models\User;
use Dashed\DashedCore\Classes\Mails;
use Illuminate\Support\Facades\Mail;
use Filament\Notifications\Notification;
use Dashed\DashedCore\Mail\NotificationMail;

/**
 * Een mail naar de meldingsadressen van de winkel plus een belletje voor de
 * beheerders. Mag nooit een inzending tegenhouden: alles via rescue().
 */
class AdminNotifier
{
    public static function notify(string $title, string $body, ?string $url = null, ?string $siteId = null): void
    {
        foreach (User::query()->whereIn('role', ['admin', 'superadmin'])->get() as $admin) {
            rescue(fn () => Notification::make()
                ->title($title)
                ->body($body)
                ->info()
                ->actions($url ? [Action::make('open')->label(__('Bekijken'))->url($url)] : [])
                ->sendToDatabase($admin), report: false);
        }

        $emails = rescue(fn () => (array) Mails::getAdminNotificationEmails(), [], false);

        foreach ($emails as $email) {
            rescue(fn () => Mail::to($email)->send(new NotificationMail($body . ($url ? "\n\n" . $url : ''), $title)), report: false);
        }
    }
}
