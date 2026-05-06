<?php

namespace Dashed\DashedEcommerceCore\Exports;

use Maatwebsite\Excel\Concerns\FromArray;

class OrderListExport implements FromArray
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
                'Order ID',
                'Voornaam',
                'Achternaam',
                'Email',
                'Straat',
                'Huisnummer',
                'Postcode',
                'Stad',
                'Land',
                'Telefoonnummer',
                'Geboortedatum',
                'Initialen',
                'Geslacht',
                'Bedrijfsnaam',
                'BTW ID',
                'Notitie',
                'Factuur voornaam',
                'Factuur achternaam',
                'Factuur straat',
                'Factuur huisnummer',
                'Factuur postcode',
                'Factuur stad',
                'Factuur land',
                'Factuur nummer',
                'Totaal',
                'Subtotaal',
                'BTW',
                'Korting',
                'Status',
                'Site ID',
                'Locale',
                'Bestellings herkomst',
                'Aangekocht op',
                'Gekochte producten',
                'UTM bron',
                'UTM medium',
                'UTM campagne',
                'UTM term',
                'UTM content',
                'Google Click ID',
                'Facebook Click ID',
                'Microsoft Click ID',
                'Landingspagina',
                'First-touch',
                'Last-touch',
            ],
        ];

        foreach ($this->orders as $order) {
            $products = '';

            foreach ($order->orderProducts as $orderProduct) {
                $name = $orderProduct->name;

                if ($orderProduct->product_extras) {
                    $name .= ' (';

                    foreach ($orderProduct->product_extras as $productExtra) {
                        $name .= $productExtra['name'] . ':' . $productExtra['value'] . ', ';
                    }

                    $name = rtrim($name, ', ');
                    $name .= ')';
                }

                $products .= $name . ', ';
            }

            $ordersArray[] = [
                $order->id,
                $order->first_name,
                $order->last_name,
                $order->email,
                $order->street,
                $order->house_nr,
                $order->zip_code,
                $order->city,
                $order->country,
                $order->phone_number,
                $order->birth_date,
                $order->initials,
                $order->gender,
                $order->company,
                $order->btw_id,
                $order->note,
                $order->invoice_first_name,
                $order->invoice_last_name,
                $order->invoice_street,
                $order->invoice_house_nr,
                $order->invoice_zip_code,
                $order->invoice_city,
                $order->invoice_country,
                $order->invoice_id,
                $order->total,
                $order->subtotal,
                $order->btw,
                $order->discount,
                $order->status,
                $order->site_id,
                $order->locale,
                $order->order_origin,
                $order->created_at,
                $products,
                $order->utm_source,
                $order->utm_medium,
                $order->utm_campaign,
                $order->utm_term,
                $order->utm_content,
                $order->gclid,
                $order->fbclid,
                $order->msclkid,
                $order->landing_page,
                optional($order->attribution_first_touch_at)->toIso8601String(),
                optional($order->attribution_last_touch_at)->toIso8601String(),
            ];
        }

        return [
            $ordersArray,
        ];
    }
}
