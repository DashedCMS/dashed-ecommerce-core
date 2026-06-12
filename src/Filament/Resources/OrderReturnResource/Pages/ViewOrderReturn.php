<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources\OrderReturnResource\Pages;

use Filament\Actions\Action;
use Illuminate\Database\Eloquent\Model;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Pages\ViewRecord;
use Filament\Notifications\Notification;
use Dashed\DashedEcommerceCore\Models\OrderReturn;
use Dashed\DashedEcommerceCore\Filament\Resources\OrderReturnResource;

class ViewOrderReturn extends ViewRecord
{
    protected static string $resource = OrderReturnResource::class;

    protected function resolveRecord(int | string $key): Model
    {
        return static::getResource()::getEloquentQuery()
            ->with(['order', 'lines.orderProduct', 'lines.returnReason'])
            ->findOrFail($key);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('approve')
                ->label('Goedkeuren')
                ->color('success')
                ->visible(fn () => $this->getRecord()->status === OrderReturn::STATUS_REQUESTED)
                ->schema([
                    Textarea::make('admin_note')
                        ->label('Notitie (optioneel)'),
                ])
                ->action(function (array $data) {
                    $this->getRecord()->approve($data['admin_note'] ?? null);
                    $this->getRecord()->refresh();

                    Notification::make()
                        ->success()
                        ->title('Retouraanvraag goedgekeurd')
                        ->send();
                }),
            Action::make('reject')
                ->label('Afkeuren')
                ->color('danger')
                ->visible(fn () => $this->getRecord()->status === OrderReturn::STATUS_REQUESTED)
                ->schema([
                    Textarea::make('rejected_reason')
                        ->label('Reden')
                        ->required(),
                ])
                ->action(function (array $data) {
                    $this->getRecord()->reject($data['rejected_reason']);
                    $this->getRecord()->refresh();

                    Notification::make()
                        ->success()
                        ->title('Retouraanvraag afgekeurd')
                        ->send();
                }),
            Action::make('markHandled')
                ->label('Markeer als afgehandeld')
                ->color('gray')
                ->visible(fn () => in_array($this->getRecord()->status, [OrderReturn::STATUS_REQUESTED, OrderReturn::STATUS_APPROVED]))
                ->requiresConfirmation()
                ->action(function () {
                    $this->getRecord()->markHandled();
                    $this->getRecord()->refresh();

                    Notification::make()
                        ->success()
                        ->title('Retouraanvraag gemarkeerd als afgehandeld')
                        ->send();
                }),
        ];
    }
}
