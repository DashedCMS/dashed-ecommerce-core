<?php

namespace Dashed\DashedEcommerceCore\Livewire\Orders;

use Livewire\Component;
use Filament\Actions\Action;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Actions\Contracts\HasActions;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Classes\Orders;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Actions\Concerns\InteractsWithActions;

class SendOrderConfirmationToEmail extends Component implements HasForms, HasActions
{
    use InteractsWithForms;
    use InteractsWithActions;

    public Order $order;

    public function mount(Order $order)
    {
        $this->order = $order;
    }

    public function action(): Action
    {
        return Action::make('action')
            ->label('Stuur email bevestiging')
            ->color('primary')
            ->fillForm(function () {
                return [
                    'email' => $this->order->email,
                ];
            })
            ->form([
                TextInput::make('email')
                    ->label('Bestel bevestiging versturen naar')
                    ->required()
                    ->email(),
            ])
            ->action(function ($data) {
                Orders::sendNotification($this->order, $data['email'], auth()->user());

                Notification::make()
                    ->success()
                    ->title('De notificatie is verstuurd')
                    ->send();

                $this->dispatch('refreshData');
            });
    }

    public function render()
    {
        return view('dashed-ecommerce-core::orders.components.plain-action');
    }
}
