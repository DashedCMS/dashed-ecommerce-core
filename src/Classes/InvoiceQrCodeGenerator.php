<?php

namespace Dashed\DashedEcommerceCore\Classes;

use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Dashed\DashedEcommerceCore\Models\Order;

class InvoiceQrCodeGenerator
{
    public static function for(Order $order): ?string
    {
        if ($order->outstandingAmount() <= 0) {
            return null;
        }

        $options = new QROptions([
            'outputType' => QRCode::OUTPUT_MARKUP_SVG,
            'eccLevel' => QRCode::ECC_M,
            'imageBase64' => true,
        ]);

        return (new QRCode($options))->render(self::urlFor($order));
    }

    public static function urlFor(Order $order): string
    {
        return url('/pay/order/' . $order->hash . '/remainder');
    }
}
