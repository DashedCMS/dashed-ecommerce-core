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

class ChangeOrderRetourStatus extends Component implements HasForms, HasActions
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
            ->label('Verander retour status')
            ->color('primary')
            ->fillForm(function () {
                return [
                    'retourStatus' => $this->order->retour_status,
                ];
            })
            ->form([
                Select::make('retourStatus')
                    ->label('Verander retour status')
                    ->options(Orders::getReturnStatusses())
                    ->required(),
            ])
            ->action(function ($data) {
                $this->order->retour_status = $data['retourStatus'];
                $this->order->save();

                $orderLog = new OrderLog();
                $orderLog->order_id = $this->order->id;
                $orderLog->user_id = Auth::user()->id;
                $orderLog->tag = 'order.changed-retour-status-to-' . $data['retourStatus'];
                $orderLog->save();

                Notification::make()
                    ->success()
                    ->title('Bestelling retour status aangepast')
                    ->send();

                $this->dispatch('refreshData');
            });
    }

    public function render()
    {
        return view('dashed-ecommerce-core::orders.components.plain-action');
    }
}
