<?php

namespace Qubiqx\QcommerceEcommerceCore\Livewire\Orders;

use Filament\Notifications\Notification;
use Livewire\Component;
use Qubiqx\QcommerceEcommerceCore\Classes\Orders;

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
        return view('qcommerce-ecommerce-core::orders.components.send-order-confirmation-to-email');
    }

    public function submit()
    {
        $this->validate();

        Orders::sendNotification($this->order, $this->email);

        $this->emit('refreshPage');
        Notification::make()
            ->success()
            ->title('De notificatie is verstuurd')
            ->send();
    }
}
