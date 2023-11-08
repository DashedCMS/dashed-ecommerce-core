<?php

namespace Dashed\DashedEcommerceCore\Livewire\Orders;

use Livewire\Component;
use Filament\Actions\Action;
use Illuminate\Support\Facades\Auth;
use Filament\Forms\Components\Select;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Actions\Contracts\HasActions;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Classes\Orders;
use Dashed\DashedEcommerceCore\Models\OrderLog;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Actions\Concerns\InteractsWithActions;

class ChangeOrderFulfillmentStatus extends Component implements HasForms, HasActions
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
            ->label('Verander fulfilment status')
            ->color('primary')
            ->fillForm(function () {
                return [
                    'fulfillmentStatus' => $this->order->fulfillment_status,
                ];
            })
            ->form([
                Select::make('fulfillmentStatus')
                    ->label('Verander fulfilment status')
                    ->options(Orders::getFulfillmentStatusses())
                    ->required(),
            ])
            ->action(function ($data) {
                if ($this->order->fulfillment_status == $data['fulfillmentStatus']) {
                    Notification::make()
                        ->danger()
                        ->title('Bestelling heeft al deze fulfillment status')
                        ->send();

                    return;
                }

                $this->order->changeFulfillmentStatus($data['fulfillmentStatus']);

                $orderLog = new OrderLog();
                $orderLog->order_id = $this->order->id;
                $orderLog->user_id = Auth::user()->id;
                $orderLog->tag = 'order.changed-fulfillment-status-to-' . $data['fulfillmentStatus'];
                $orderLog->save();

                Notification::make()
                    ->success()
                    ->title('Bestelling fulfillment status aangepast')
                    ->send();

                $this->dispatch('refreshData');
            });
    }

    public function render()
    {
        return view('dashed-ecommerce-core::orders.components.plain-action');
    }
}
