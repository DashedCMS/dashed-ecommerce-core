<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Filament\Resources\PrinterResource\Pages;

use Dashed\DashedEcommerceCore\Filament\Pages\Settings\PrintQueueSettingsPage;
use Dashed\DashedEcommerceCore\Filament\Resources\PrinterResource;
use Dashed\DashedEcommerceCore\Models\Printer;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListPrinters extends ListRecords
{
    protected static string $resource = PrinterResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('pair_new_pi')
                ->label('Pair een nieuwe Raspberry Pi')
                ->icon('heroicon-o-plus-circle')
                ->color('success')
                ->action(function (): void {
                    Printer::startPairing();

                    Notification::make()
                        ->title('Pairing code aangemaakt')
                        ->body('Open Print queue instellingen om de installatie-oneliner te kopiëren.')
                        ->success()
                        ->send();

                    $this->redirect(PrintQueueSettingsPage::getUrl());
                }),
        ];
    }
}
