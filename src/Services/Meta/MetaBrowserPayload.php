<?php

namespace Dashed\DashedEcommerceCore\Services\Meta;

use Dashed\DashedEcommerceCore\Models\Order;

/**
 * Wat de bedankpagina aan de browserpixel meegeeft om met het server-side
 * event te ontdubbelen: hetzelfde event-ID, dezelfde waarde, dezelfde inhoud.
 * Het event-ID gaat altijd mee; de value mode alleen als de Conversions API
 * aan staat, zodat een uitgeschakelde shop dezelfde waarde blijft sturen.
 */
class MetaBrowserPayload
{
    /** @return array{metaEventId: string, metaValue: string, metaContents: array<int, array<string, mixed>>} */
    public static function forOrder(Order $order): array
    {
        $settings = MetaCapiSettings::for($order->site_id);
        $valueMode = $settings->enabled() ? $settings->valueMode() : MetaCapiSettings::VALUE_INCL_VAT;

        return [
            'metaEventId' => ConversionsApi::eventId($order),
            'metaValue' => number_format(MetaPurchaseValue::for($order, $valueMode), 2, '.', ''),
            'metaContents' => MetaPurchaseValue::contents($order),
        ];
    }
}
