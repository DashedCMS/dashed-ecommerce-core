<?php

namespace Qubiqx\QcommerceEcommerceCore\Livewire\Orders;

use Livewire\Component;

class AddPaymentToOrder extends Component
{
    public $order;
    public $paymentAmount;

    protected $rules = [
      'paymentAmount' => [
          'required',
          'numeric',
          'min:0.01',
      ],
    ];

    public function mount($order)
    {
        $this->order = $order;
        $this->paymentAmount = $order->total - $order->orderPayments->where('status', 'paid')->sum('amount');
        if ($this->paymentAmount < 0) {
            $this->paymentAmount = 0;
        }
    }

    public function render()
    {
        return view('qcommerce-ecommerce-core::orders.components.add-payment-to-order');
    }

    public function submit()
    {
        $this->validate();

        if ($this->order->status != 'paid') {
            $orderPayment = $this->order->orderPayments()->create([
                'psp' => 'own',
                'payment_method' => 'manual_payment',
                'amount' => $this->paymentAmount,
            ]);

            $newPaymentStatus = $orderPayment->changeStatus('paid');
            $this->order->changeStatus($newPaymentStatus);
        }

        $this->emit('refreshPage');
        $this->emit('notify', [
            'status' => 'success',
            'message' => 'Bestelling gemarkeerd als betaald',
        ]);
    }
}
