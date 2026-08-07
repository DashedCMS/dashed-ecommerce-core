<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources\OrderReturnResource\Pages;

use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Illuminate\Database\Eloquent\Model;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Placeholder;
use Dashed\DashedCore\Models\EmailTemplate;
use Dashed\DashedEcommerceCore\Models\OrderReturn;
use Dashed\DashedEcommerceCore\Filament\Resources\OrderReturnResource;
use Dashed\DashedEcommerceCore\Mail\OrderReturn\OrderReturnCustomMail;

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
                ->label(__('Goedkeuren'))
                ->color('success')
                ->visible(fn () => $this->getRecord()->status === OrderReturn::STATUS_REQUESTED)
                ->schema([
                    Textarea::make('admin_note')
                        ->label(__('Notitie (optioneel)')),
                ])
                ->action(function (array $data) {
                    $this->getRecord()->approve($data['admin_note'] ?? null);
                    $this->getRecord()->refresh();

                    Notification::make()
                        ->success()
                        ->title(__('Retouraanvraag goedgekeurd'))
                        ->send();
                }),
            Action::make('reject')
                ->label(__('Afkeuren'))
                ->color('danger')
                ->visible(fn () => $this->getRecord()->status === OrderReturn::STATUS_REQUESTED)
                ->schema([
                    Textarea::make('rejected_reason')
                        ->label(__('Reden'))
                        ->required(),
                ])
                ->action(function (array $data) {
                    $this->getRecord()->reject($data['rejected_reason']);
                    $this->getRecord()->refresh();

                    Notification::make()
                        ->success()
                        ->title(__('Retouraanvraag afgekeurd'))
                        ->send();
                }),
            Action::make('markHandled')
                ->label(__('Markeer als afgehandeld'))
                ->color('gray')
                ->visible(fn () => in_array($this->getRecord()->status, [OrderReturn::STATUS_REQUESTED, OrderReturn::STATUS_APPROVED]))
                ->requiresConfirmation()
                ->action(function () {
                    $this->getRecord()->markHandled();
                    $this->getRecord()->refresh();

                    Notification::make()
                        ->success()
                        ->title(__('Retouraanvraag gemarkeerd als afgehandeld'))
                        ->send();
                }),
            Action::make('sendEmail')
                ->label(__('Stuur e-mail naar klant'))
                ->icon('heroicon-o-envelope')
                ->color('primary')
                ->schema([
                    TextInput::make('email')
                        ->label(__('E-mailadres'))
                        ->email()
                        ->required()
                        ->default(fn () => $this->getRecord()->email),
                    TextInput::make('subject')
                        ->label(__('Onderwerp'))
                        ->required()
                        ->default(function () {
                            $template = EmailTemplate::forMailable(OrderReturnCustomMail::emailTemplateKey());

                            return $template?->getTranslation('subject', app()->getLocale(), useFallbackLocale: true)
                                ?: OrderReturnCustomMail::defaultSubject();
                        }),
                    Placeholder::make('variabelen')
                        ->label(__('Beschikbare variabelen'))
                        ->content(fn () => OrderReturnCustomMail::usableVariablesHint()),
                    RichEditor::make('message')
                        ->label(__('Bericht'))
                        ->required()
                        ->default(fn () => OrderReturnCustomMail::defaultMessage()),
                ])
                ->action(function (array $data) {
                    $this->getRecord()->sendCustomEmail($data['subject'], $data['message'], $data['email']);

                    Notification::make()
                        ->success()
                        ->title(__('Bericht naar klant verstuurd'))
                        ->send();
                }),
        ];
    }
}
