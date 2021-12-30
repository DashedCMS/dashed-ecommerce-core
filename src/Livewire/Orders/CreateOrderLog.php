<?php

namespace Qubiqx\QcommerceEcommerceCore\Livewire\Orders;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Livewire\Component;
use Qubiqx\QcommerceEcommerceCore\Classes\Orders;
use Qubiqx\QcommerceEcommerceCore\Mail\OrderNoteMail;
use Qubiqx\QcommerceEcommerceCore\Models\OrderLog;

class CreateOrderLog extends Component
{
    public $order;
    public $publicForCustomer;
    public $note;

    protected $rules = [
        'note' => [
            'required',
            'min:3',
            'max:1500',
        ],
    ];

    public function mount($order)
    {
        $this->order = $order;
    }

    public function render()
    {
        return view('qcommerce-ecommerce-core::orders.components.create-order-log');
    }

    public function submit()
    {
        $this->validate();

        $orderLog = new OrderLog();
        $orderLog->order_id = $this->order->id;
        $orderLog->user_id = Auth::user()->id;
        $orderLog->tag = 'order.note.created';
        $orderLog->note = $this->note;
        $orderLog->public_for_customer = $this->publicForCustomer ? 1 : 0;
        $orderLog->save();

        if ($orderLog->public_for_customer) {
            Mail::to($this->order->email)->send(new OrderNoteMail($this->order, $orderLog));
        }

        $this->emit('refreshPage');
        $this->emit('notify', [
            'status' => 'success',
            'message' => 'De notificatie is aangemaakt',
        ]);

        $this->note = null;
        $this->publicForCustomer = null;
    }
}
