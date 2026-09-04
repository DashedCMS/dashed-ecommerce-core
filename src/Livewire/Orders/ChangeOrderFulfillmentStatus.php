<?php

namespace Dashed\DashedEcommerceCore\Livewire\Orders;

use Livewire\Component;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Actions\Contracts\HasActions;
use Filament\Schemas\Contracts\HasSchemas;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Classes\Orders;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Schemas\Concerns\InteractsWithSchemas;

class ChangeOrderFulfillmentStatus extends Component implements HasSchemas, HasActions
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
            ->label(__('Verander fulfilment status'))
            ->color('primary')
            ->fillForm(function () {
                return [
                    'fulfillmentStatus' => $this->order->fulfillment_status,
                ];
            })
            ->schema([
                Select::make('fulfillmentStatus')
                    ->label(__('Verander fulfilment status'))
                    ->options(Orders::getFulfillmentStatusses())
                    ->required(),
            ])
            ->action(function ($data) {
                if ($this->order->fulfillment_status == $data['fulfillmentStatus']) {
                    Notification::make()
                        ->danger()
                        ->title(__('Bestelling heeft al deze fulfillment status'))
                        ->send();

                    return;
                }

                // changeFulfillmentStatus() logt de wijziging zelf in de orderlogs.
                $this->order->changeFulfillmentStatus($data['fulfillmentStatus']);

                Notification::make()
                    ->success()
                    ->title(__('Bestelling fulfillment status aangepast'))
                    ->send();

                $this->dispatch('refreshData');
            });
    }

    public function render()
    {
        return view('dashed-ecommerce-core::orders.components.plain-action');
    }
}
