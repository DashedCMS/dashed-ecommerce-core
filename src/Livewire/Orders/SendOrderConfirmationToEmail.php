<?php

namespace Dashed\DashedEcommerceCore\Livewire\Orders;

use Livewire\Component;
use Filament\Notifications\Notification;
use Dashed\DashedEcommerceCore\Classes\Orders;

class SendOrderConfirmationToEmail extends Component
{
    public $order;
    public $email;

    protected $rules = [
      'email' => [
          'required',
          'email:rfc',
      ],
    ];

    public function mount($order)
    {
        $this->order = $order;
        $this->email = $order->email;
    }

    public function render()
    {
        return view('dashed-ecommerce-core::orders.components.send-order-confirmation-to-email');
    }

    public function submit()
    {
        $this->validate();

        Orders::sendNotification($this->order, $this->email, auth()->user());

        $this->emit('refreshPage');
        Notification::make()
            ->success()
            ->title('De notificatie is verstuurd')
            ->send();
    }
}
