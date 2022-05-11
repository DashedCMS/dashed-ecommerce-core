<?php

namespace Qubiqx\QcommerceEcommerceCore\Exports;

use Maatwebsite\Excel\Concerns\FromArray;

class OrderListPerInvoiceLineExport implements FromArray
{
    protected $orders;

    public function __construct($orders)
    {
        $this->orders = $orders;
    }

    public function array(): array
    {
        $ordersArray = [
            [
                'Dag',
                'Maand',
                'Jaar',
                'Order ID',
                'Factuur ID',
                'PSP ID',
                'Email',
                'Product',
                'Prijs incl BTW',
                'BTW',
                'Prijs EX BTW',
            ],
        ];

        foreach ($this->orders as $order) {
            foreach($order->orderProducts as $orderProduct){
                $quantity = $orderProduct->quantity;
                while($quantity > 0){
                    $ordersArray[] = [
                        $order->created_at->day,
                        $order->created_at->month,
                        $order->created_at->year,
                        $order->id,
                        $order->invoice_id,
                        $order->psp_id,
                        $order->email,
                        $orderProduct->name,
                        $orderProduct->price / $orderProduct->quantity,
                        $orderProduct->btw / $orderProduct->quantity,
                        ($orderProduct->price - $orderProduct->btw) / $orderProduct->quantity,
                    ];

                    $quantity--;
                }
            }
        }

        return [
            $ordersArray,
        ];
    }
}
