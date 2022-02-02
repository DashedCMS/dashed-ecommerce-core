<?php

namespace Qubiqx\QcommerceEcommerceCore\Livewire\Orders;

use Livewire\Component;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Qubiqx\QcommerceEcommerceCore\Models\OrderLog;
use Qubiqx\QcommerceEcommerceCore\Mail\OrderNoteMail;

class CreateOrderLog extends Component
{
    use WithFileUploads;

    public $order;
    public $publicForCustomer;
    public $images;
    public $note;

    protected $rules = [
        'note' => [
            'required',
            'min:3',
            'max:1500',
        ],
        'images.*' => [
            'mimes:jpg,png,jpeg,pdf',
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

        $images = [];
        foreach ($this->images as $image) {
            $uploadedImage = $image->store('/qcommerce/orders/logs/images');
            $images[] = $uploadedImage;
        }

        $orderLog->images = $images;
        $orderLog->save();

        if ($orderLog->public_for_customer) {
            try {
                Mail::to($this->order->email)->send(new OrderNoteMail($this->order, $orderLog));
            } catch (\Exception $exception) {
            }
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
