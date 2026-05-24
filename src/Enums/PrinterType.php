<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Enums;

enum PrinterType: string
{
    case PackingSlip = 'packing_slip';
    case ShippingLabel = 'shipping_label';
    case Both = 'both';

    public function label(): string
    {
        return match ($this) {
            self::PackingSlip => 'Pakbon',
            self::ShippingLabel => 'Verzendlabel',
            self::Both => 'Pakbon en verzendlabel',
        };
    }

    public static function options(): array
    {
        return array_combine(
            array_map(fn (self $c) => $c->value, self::cases()),
            array_map(fn (self $c) => $c->label(), self::cases()),
        );
    }
}
