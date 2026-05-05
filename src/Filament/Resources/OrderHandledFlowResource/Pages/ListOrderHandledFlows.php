<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources\OrderHandledFlowResource\Pages;

use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Dashed\DashedEcommerceCore\Models\OrderHandledFlow;
use Dashed\DashedEcommerceCore\Filament\Resources\OrderHandledFlowResource;

class ListOrderHandledFlows extends ListRecords
{
    protected static string $resource = OrderHandledFlowResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('create_default')
                ->label('Maak standaard flow aan')
                ->icon('heroicon-o-sparkles')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Standaard flow aanmaken')
                ->modalDescription('Maakt een nieuwe flow aan met 1 stap (14 dagen na fulfillment_status = handled) en stelt deze in als actieve flow. Andere flows worden automatisch op inactive gezet.')
                ->modalSubmitActionLabel('Aanmaken')
                ->action(function () {
                    OrderHandledFlow::createDefault();

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
