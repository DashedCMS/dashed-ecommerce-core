<?php

namespace Dashed\DashedEcommerceCore\Livewire\Orders;

use Livewire\Component;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Actions\Contracts\HasActions;
use Filament\Schemas\Contracts\HasSchemas;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Classes\Orders;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Schemas\Concerns\InteractsWithSchemas;

class SendOrderConfirmationToEmail extends Component implements HasSchemas, HasActions
{
    use InteractsWithSchemas;
    use InteractsWithActions;

    public Order $order;
    public string $color = 'primary';

    public function mount(Order $order, string $color = 'primary')
    {
        $this->order = $order;
        $this->color = $color;
    }

    public function action(): Action
    {
        return Action::make('action')
            // Alleen met "Bestellingen bewerken"; authorize() verbergt de knop
            // en weigert hem ook bij een rechtstreekse aanroep.
            ->authorize(fn (): bool => auth()->user()?->can('update', $this->order) ?? false)
            ->label(__('Stuur email bevestiging'))
            ->color($this->color)
            ->fillForm(function () {
                return [
                    'email' => $this->order->email,
                ];
            })
            ->schema([
                TextInput::make('email')
                    ->label(__('Bestel bevestiging versturen naar'))
                    ->required()
                    ->email(),
            ])
            ->action(function ($data) {
                if (Orders::sendNotification($this->order, $data['email'], auth()->user())) {
                    Notification::make()
                        ->success()
                        ->title(__('De notificatie is verstuurd'))
                        ->send();
                } else {
                    Notification::make()
                        ->danger()
                        ->title(__('De bevestigingsmail kon niet worden verstuurd'))
                        ->body(__('De reden staat in het logboek onderaan deze bestelling, bij de regel "heeft de bestellings mail NIET laten versturen".'))
                        ->send();
                }

                $this->dispatch('refreshData');
            });
    }

    public function render()
    {
        return view('dashed-ecommerce-core::orders.components.plain-action');
    }
}
