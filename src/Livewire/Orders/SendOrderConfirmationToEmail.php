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
            ->schema([
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
