<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources\OrderResource\Pages;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Dashed\DashedEcommerceCore\Models\AbandonedCartEmail;
use Dashed\DashedEcommerceCore\Filament\Resources\OrderResource;

class EditOrder extends EditRecord
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('stopRecoveryEmails')
                ->label('Stop herstel-emails')
                ->icon('heroicon-m-no-symbol')
                ->color('warning')
                ->visible(fn () => $this->record->email && AbandonedCartEmail::query()
                    ->where('email', $this->record->email)
                    ->whereNull('sent_at')
                    ->whereNull('cancelled_at')
                    ->exists())
                ->requiresConfirmation()
                ->modalHeading('Herstel-emails stoppen')
                ->modalDescription('Alle openstaande herstel-emails voor deze klant op dit email-adres worden geannuleerd.')
                ->action(function () {
                    $count = AbandonedCartEmail::cancelPendingForEmail($this->record->email, 'manual_admin');
                    Notification::make()
                        ->title("{$count} geplande herstel-email(s) geannuleerd")
                        ->success()
                        ->send();
                }),
        ];
    }
}
