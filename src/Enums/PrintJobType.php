<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Enums;

enum PrintJobType: string
{
    case PackingSlip = 'packing_slip';
    case ShippingLabel = 'shipping_label';

    public function label(): string
    {
        return match ($this) {
            self::PackingSlip => 'Pakbon',
            self::ShippingLabel => 'Verzendlabel',
        };
    }
}
