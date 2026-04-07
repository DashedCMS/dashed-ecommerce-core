<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources\AbandonedCartFlowResource\Pages;

use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Dashed\DashedEcommerceCore\Models\AbandonedCartFlow;
use Dashed\DashedEcommerceCore\Filament\Resources\AbandonedCartFlowResource;

class ListAbandonedCartFlows extends ListRecords
{
    protected static string $resource = AbandonedCartFlowResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('create_default')
                ->label('Maak standaard flow aan')
                ->icon('heroicon-o-sparkles')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Standaard flow aanmaken')
                ->modalDescription('Dit maakt een nieuwe flow aan met 3 stappen (1 uur, 24 uur en 72 uur na verlaten) en stelt deze in als actieve flow.')
                ->modalSubmitActionLabel('Aanmaken')
                ->action(function () {
                    AbandonedCartFlow::createDefault();

                    Notification::make()
                        ->title('Standaard flow aangemaakt en geactiveerd')
                        ->success()
                        ->send();
                }),

            CreateAction::make()
                ->label('Nieuwe flow'),
        ];
    }
}
